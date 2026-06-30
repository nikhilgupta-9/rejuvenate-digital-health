<?php
/**
 * Unified auth helper — role detection, session setup, OTP utilities.
 * Included by process-login.php, ajax/login-*.php
 */
require_once __DIR__ . '/mail_config.php';

/* ─── Role session setup ────────────────────────────────────────────── */

function setRoleSession(array $u, string $role): string
{
    $base = defined('BASE_URL') ? BASE_URL : '/';

    switch ($role) {
        case 'patient':
            $_SESSION['logged_in']    = true;
            $_SESSION['user_id']      = $u['id'];
            $_SESSION['user_name']    = $u['name'];
            $_SESSION['user_email']   = $u['email'];
            return $base . 'user/user-dashboard.php';

        case 'school_admin':
        case 'school_staff':
            $_SESSION['school_logged_in']  = true;
            $_SESSION['school_user_id']    = $u['id'];
            $_SESSION['school_id']         = $u['school_id'];
            $_SESSION['school_name']       = $u['school_name'];
            $_SESSION['school_user_name']  = $u['name'];
            $_SESSION['school_user_email'] = $u['email'];
            $_SESSION['school_user_role']  = $u['role'] ?? $role;
            return $base . 'school/dashboard.php';

        case 'student':
            $_SESSION['student_logged_in'] = true;
            $_SESSION['student_id']        = $u['id'];
            $_SESSION['student_name']      = $u['name'];
            $_SESSION['student_email']     = $u['email'] ?? '';
            $_SESSION['student_school_id'] = $u['school_id'];
            $_SESSION['student_school']    = $u['school_name'];
            $_SESSION['student_uid']       = $u['member_uid'];
            $_SESSION['student_class']     = $u['class'] ?? '';
            return $base . 'school/student/dashboard.php';

        case 'teacher':
        case 'staff':
            $_SESSION['teacher_logged_in'] = true;
            $_SESSION['teacher_id']        = $u['id'];
            $_SESSION['teacher_name']      = $u['name'];
            $_SESSION['teacher_email']     = $u['email'] ?? '';
            $_SESSION['teacher_school_id'] = $u['school_id'];
            $_SESSION['teacher_school']    = $u['school_name'];
            $_SESSION['teacher_uid']       = $u['member_uid'];
            return $base . 'school/teacher/dashboard.php';

        case 'doctor':
            $_SESSION['doctor_logged_in'] = true;
            $_SESSION['doctor_id']        = $u['id'];
            $_SESSION['doctor_name']      = $u['name'];
            $_SESSION['doctor_email']     = $u['email'];
            return $base . 'doctor/doctor-dashboard.php';
    }

    return $base . 'login.php';
}

/* Return role label for display */
function roleLabel(string $role): string
{
    return [
        'patient'      => 'Patient',
        'student'      => 'Student',
        'teacher'      => 'Teacher',
        'staff'        => 'Staff',
        'school_admin' => 'School Admin',
        'school_staff' => 'School Staff',
        'doctor'       => 'Doctor',
    ][$role] ?? ucfirst($role);
}

/* ─── Role detection by identifier ─────────────────────────────────── */

/**
 * Find a user across all tables by email, mobile, roll number, or employee ID.
 * Returns ['user' => [...], 'role' => 'patient|student|...'] or null.
 */
function findByIdentifier(mysqli $conn, string $id): ?array
{
    $id = trim($id);
    if ($id === '') return null;

    $isEmail  = strpos($id, '@') !== false;
    $isMobile = preg_match('/^\d{10}$/', preg_replace('/\D/', '', $id));

    // 1. Patients (users table) — email or mobile
    if ($isEmail || $isMobile) {
        $col = $isEmail ? 'email' : 'mobile';
        $mob = preg_replace('/\D/', '', $id); // normalize mobile
        $val = $isEmail ? $id : $mob;
        $s = $conn->prepare("SELECT * FROM users WHERE $col=? AND status='Active' LIMIT 1");
        $s->bind_param('s', $val); $s->execute();
        if ($r = $s->get_result()->fetch_assoc()) return ['user' => $r, 'role' => 'patient'];
    }

    // 2. School admins / staff (school_users) — email only
    if ($isEmail) {
        $s = $conn->prepare("
            SELECT su.*, s.school_name, s.status as school_status
            FROM school_users su JOIN schools s ON su.school_id=s.id
            WHERE su.email=? AND su.status='Active' LIMIT 1");
        $s->bind_param('s', $id); $s->execute();
        if ($r = $s->get_result()->fetch_assoc()) {
            $role = $r['role'] === 'school_admin' ? 'school_admin' : 'school_staff';
            return ['user' => $r, 'role' => $role];
        }
    }

    // 3. School members (student / teacher / staff) — email, phone, roll, employee ID, member UID
    {
        $clean = preg_replace('/\D/', '', $id);
        $s = $conn->prepare("
            SELECT sm.*, s.school_name, s.status as school_status
            FROM school_members sm JOIN schools s ON sm.school_id=s.id
            WHERE sm.status='Active'
              AND (sm.email=? OR sm.phone=? OR sm.roll_number=? OR sm.employee_id=? OR sm.member_uid=?)
            LIMIT 1");
        $s->bind_param('sssss', $id, $clean, $id, $id, $id); $s->execute();
        if ($r = $s->get_result()->fetch_assoc()) {
            $role = match(strtolower($r['type'])) {
                'student' => 'student',
                'teacher' => 'teacher',
                default   => 'staff',
            };
            return ['user' => $r, 'role' => $role];
        }

        // Check if account exists but is pending approval
        $sp = $conn->prepare("
            SELECT sm.name, sm.type, s.school_name
            FROM school_members sm JOIN schools s ON sm.school_id=s.id
            WHERE sm.status='Pending'
              AND (sm.email=? OR sm.phone=? OR sm.roll_number=? OR sm.employee_id=? OR sm.member_uid=?)
            LIMIT 1");
        $sp->bind_param('sssss', $id, $clean, $id, $id, $id); $sp->execute();
        if ($rp = $sp->get_result()->fetch_assoc()) {
            return ['user' => $rp, 'role' => 'pending_member'];
        }
    }

    // 4. Doctors — email or phone
    if ($isEmail || $isMobile) {
        $col = $isEmail ? 'email' : 'phone';
        $mob = preg_replace('/\D/', '', $id);
        $val = $isEmail ? $id : $mob;
        $s = $conn->prepare("SELECT * FROM doctors WHERE $col=? AND status='Active' AND is_verified=1 LIMIT 1");
        $s->bind_param('s', $val); $s->execute();
        if ($r = $s->get_result()->fetch_assoc()) return ['user' => $r, 'role' => 'doctor'];
    }

    return null;
}

/**
 * Find user by ABHA number across users and school_members.
 */
function findByAbha(mysqli $conn, string $abhaId): ?array
{
    $fmt = preg_replace('/\D/', '', $abhaId);
    if (strlen($fmt) === 14) {
        $fmt = substr($fmt,0,2).'-'.substr($fmt,2,4).'-'.substr($fmt,6,4).'-'.substr($fmt,10,4);
    }

    $s = $conn->prepare("SELECT * FROM users WHERE abha_id=? AND status='Active' LIMIT 1");
    $s->bind_param('s', $fmt); $s->execute();
    if ($r = $s->get_result()->fetch_assoc()) return ['user' => $r, 'role' => 'patient'];

    $s = $conn->prepare("
        SELECT sm.*, s.school_name FROM school_members sm
        JOIN schools s ON sm.school_id=s.id
        WHERE sm.abha_id=? AND sm.status='Active' LIMIT 1");
    $s->bind_param('s', $fmt); $s->execute();
    if ($r = $s->get_result()->fetch_assoc()) {
        $role = match(strtolower($r['type'])) {
            'student' => 'student',
            'teacher' => 'teacher',
            default   => 'staff',
        };
        return ['user' => $r, 'role' => $role];
    }

    return null;
}

/**
 * Find user by Aadhaar number.
 */
function findByAadhaar(mysqli $conn, string $aadhaar): ?array
{
    $clean = preg_replace('/\D/', '', $aadhaar);

    $s = $conn->prepare("SELECT * FROM users WHERE identification_type='Aadhar' AND identification_number=? AND status='Active' LIMIT 1");
    $s->bind_param('s', $clean); $s->execute();
    if ($r = $s->get_result()->fetch_assoc()) return ['user' => $r, 'role' => 'patient'];

    $s = $conn->prepare("
        SELECT sm.*, s.school_name FROM school_members sm
        JOIN schools s ON sm.school_id=s.id
        WHERE REPLACE(sm.aadhar_number,' ','')=? AND sm.status='Active' LIMIT 1");
    $s->bind_param('s', $clean); $s->execute();
    if ($r = $s->get_result()->fetch_assoc()) {
        $role = match(strtolower($r['type'])) {
            'student' => 'student',
            'teacher' => 'teacher',
            default   => 'staff',
        };
        return ['user' => $r, 'role' => $role];
    }

    return null;
}

/* ─── OTP utilities ─────────────────────────────────────────────────── */

function generateOtp(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Store OTP in login_otps and send via email.
 * $name is optional display name for the email greeting.
 */
function storeAndSendOtp(mysqli $conn, string $entityType, int $entityId, string $otp, string $mobile = '', string $email = '', string $name = ''): bool
{
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $del = $conn->prepare("DELETE FROM login_otps WHERE entity_type=? AND entity_id=?");
    $del->bind_param('si', $entityType, $entityId);
    $del->execute();

    $ins = $conn->prepare("INSERT INTO login_otps (entity_type, entity_id, mobile, email, otp_code, otp_expiry) VALUES (?,?,?,?,?,?)");
    $ins->bind_param('sissss', $entityType, $entityId, $mobile, $email, $otp, $expiry);
    $stored = $ins->execute();

    if ($email) {
        send_otp_email($email, $otp, $name, 'login');
    }

    return $stored;
}

/**
 * Verify OTP. Returns entity info or null.
 */
function verifyStoredOtp(mysqli $conn, string $mobile, string $otp): ?array
{
    $s = $conn->prepare("
        SELECT * FROM login_otps
        WHERE mobile=? AND otp_code=? AND used=0 AND otp_expiry > NOW()
        ORDER BY created_at DESC LIMIT 1");
    $s->bind_param('ss', $mobile, $otp); $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if (!$row) return null;

    // Mark used
    $conn->query("UPDATE login_otps SET used=1 WHERE id={$row['id']}");
    return $row;
}

/* ─── Account lock check ────────────────────────────────────────────── */

function checkLock(array $user): ?string
{
    if (!empty($user['is_locked']) && !empty($user['locked_until'])
        && strtotime($user['locked_until']) > time()) {
        return 'Account locked until ' . date('h:i A', strtotime($user['locked_until'])) . '. Too many failed attempts.';
    }
    return null;
}

function incrementAttempts(mysqli $conn, string $table, int $id): void
{
    $conn->query("UPDATE $table SET login_attempts=login_attempts+1 WHERE id=$id");
    $row = $conn->query("SELECT login_attempts FROM $table WHERE id=$id")->fetch_assoc();
    if (($row['login_attempts'] ?? 0) >= 5) {
        $conn->query("UPDATE $table SET is_locked=1, locked_until=DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id=$id");
    }
}

function resetAttempts(mysqli $conn, string $table, int $id): void
{
    $conn->query("UPDATE $table SET login_attempts=0, is_locked=0, locked_until=NULL, last_login=NOW() WHERE id=$id");
}

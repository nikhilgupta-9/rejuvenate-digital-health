<?php
/**
 * Global Mail Delivery System
 * All outgoing emails go through this file.
 * SMTP settings come from .env — never hardcode credentials here.
 *
 * Usage:
 *   $mail = new Mailer();
 *   $mail->sendOtp($toEmail, $toName, $otpCode, 'login');
 *   $mail->sendPasswordReset($toEmail, $toName, $resetLink);
 *   ... etc.
 *
 * Or use the standalone helpers at the bottom of this file:
 *   send_otp_email($email, $otp);
 *   send_password_reset_email($email, $name, $link);
 *   ... etc.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/../vendor/autoload.php';

/* ─────────────────────────────────────────────
   Load .env (safe to call multiple times)
───────────────────────────────────────────── */
if (class_exists(\Dotenv\Dotenv::class) && !isset($_ENV['SITE'])) {
    $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

/* ─────────────────────────────────────────────
   SMTP constants — read once from .env
   Override any of these in .env:
     MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION,
     MAIL_USERNAME, MAIL_PASSWORD,
     MAIL_FROM_ADDRESS, MAIL_FROM_NAME
───────────────────────────────────────────── */
if (!defined('MAIL_HOST')) {
    define('MAIL_HOST',         $_ENV['MAIL_HOST']         ?? 'smtp.hostinger.com');
    define('MAIL_PORT',         (int)($_ENV['MAIL_PORT']   ?? 465));
    define('MAIL_ENCRYPTION',   $_ENV['MAIL_ENCRYPTION']   ?? 'ssl');   // ssl | tls
    define('MAIL_USERNAME',     $_ENV['MAIL_USERNAME']     ?? 'support@rejuvenatedigitalhealth.com');
    define('MAIL_PASSWORD',     $_ENV['MAIL_PASSWORD']     ?? $_ENV['EMAIL_PASSWORD'] ?? '');
    define('MAIL_FROM_ADDRESS', $_ENV['MAIL_FROM_ADDRESS'] ?? 'support@rejuvenatedigitalhealth.com');
    define('MAIL_FROM_NAME',    $_ENV['MAIL_FROM_NAME']    ?? 'REJUVENATE Digital Health');
    define('MAIL_REPLY_TO',     $_ENV['MAIL_REPLY_TO']     ?? 'support@rejuvenatedigitalhealth.com');
    define('APP_SITE_URL',      rtrim($_ENV['SITE'] ?? $_ENV['BASE_URL'] ?? 'http://localhost/rejuvenate-digital-health/', '/') . '/');
}

/* ═══════════════════════════════════════════════════════════════
   Mailer class
═══════════════════════════════════════════════════════════════ */
class Mailer
{
    private PHPMailer $ph;

    public function __construct()
    {
        $this->ph = new PHPMailer(true);
        $this->ph->isSMTP();
        $this->ph->Host      = MAIL_HOST;
        $this->ph->Port      = MAIL_PORT;
        $this->ph->SMTPAuth  = true;
        $this->ph->Username  = MAIL_USERNAME;
        $this->ph->Password  = MAIL_PASSWORD;
        $this->ph->SMTPSecure = MAIL_ENCRYPTION === 'tls'
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;

        $this->ph->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $this->ph->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
        $this->ph->isHTML(true);
        $this->ph->CharSet  = 'UTF-8';
        $this->ph->SMTPDebug = 0;
    }

    /* ── internal: set recipient and send ── */
    private function send(string $toEmail, string $toName, string $subject, string $html, string $text): bool
    {
        try {
            $this->ph->clearAddresses();
            $this->ph->addAddress($toEmail, $toName);
            $this->ph->Subject = $subject;
            $this->ph->Body    = $html;
            $this->ph->AltBody = $text;
            $this->ph->send();
            return true;
        } catch (MailerException $e) {
            error_log('[Mailer] Send failed to ' . $toEmail . ': ' . $this->ph->ErrorInfo);
            return false;
        }
    }

    /* ══════════════════════════════════════════
       1. OTP Email
       $context = 'login' | 'signup' | 'reset' | 'abha' | 'change_email'
    ══════════════════════════════════════════ */
    public function sendOtp(string $toEmail, string $toName, string $otp, string $context = 'login'): bool
    {
        $labels = [
            'login'         => ['Login Verification',          'Sign in to your account'],
            'signup'        => ['Email Verification',          'Complete your registration'],
            'reset'         => ['Password Reset Verification', 'Reset your account password'],
            'abha'          => ['ABHA Verification',           'Verify your ABHA account'],
            'change_email'  => ['Email Change Verification',   'Confirm your new email address'],
            'doctor_lookup' => ['Doctor Record Access Request', 'A doctor is requesting to view your health record'],
        ];

        [$title, $subtitle] = $labels[$context] ?? ['OTP Verification', 'Verify your identity'];

        $expiry    = 10;
        $firstName = explode(' ', trim($toName))[0] ?: 'User';

        $html = $this->layout(
            $title,
            $subtitle,
            "
            <p>Hello <strong>{$firstName}</strong>,</p>
            <p>Use the one-time password below to {$subtitle}. It expires in <strong>{$expiry} minutes</strong>.</p>

            <div style='text-align:center;margin:28px 0;'>
              <div style='display:inline-block;background:#f0f7ff;border:2px dashed #0C74C5;border-radius:12px;
                          padding:20px 40px;font-size:36px;font-weight:800;letter-spacing:12px;
                          color:#0C74C5;font-family:monospace;'>
                {$otp}
              </div>
            </div>

            <div style='background:#fff8e1;border-left:4px solid #f59e0b;padding:12px 16px;border-radius:6px;margin:20px 0;font-size:13px;'>
              <strong>Security reminder:</strong> Never share this OTP with anyone.
              Our team will never ask for your OTP. This code expires in {$expiry} minutes.
            </div>

            <p style='color:#6b7280;font-size:13px;'>If you did not request this, please ignore this email or
            <a href='mailto:" . MAIL_REPLY_TO . "' style='color:#0C74C5;'>contact support</a>.</p>
            ",
            "Your {$title} OTP is: {$otp}\nExpires in {$expiry} minutes.\nDo not share this code with anyone."
        );

        return $this->send(
            $toEmail,
            $toName,
            "{$otp} is your REJUVENATE Digital Health OTP",
            $html,
            "Your OTP: {$otp} (expires in {$expiry} minutes)"
        );
    }

    /* ══════════════════════════════════════════
       2. Password Reset Email (sends link)
    ══════════════════════════════════════════ */
    public function sendPasswordReset(string $toEmail, string $toName, string $resetLink, int $expiryMinutes = 30): bool
    {
        $firstName = explode(' ', trim($toName))[0] ?: 'User';

        $html = $this->layout(
            'Password Reset Request',
            'Reset your account password',
            "
            <p>Hello <strong>{$firstName}</strong>,</p>
            <p>We received a request to reset the password for your account. Click the button below to choose a new password.</p>

            <div style='text-align:center;margin:28px 0;'>
              <a href='{$resetLink}' style='background:#0C74C5;color:#fff;text-decoration:none;
                 padding:14px 36px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;'>
                Reset My Password
              </a>
            </div>

            <p style='font-size:13px;color:#6b7280;'>Or copy and paste this link into your browser:</p>
            <p style='word-break:break-all;font-size:12px;background:#f3f4f6;padding:10px 14px;border-radius:6px;color:#374151;'>{$resetLink}</p>

            <div style='background:#fef3c7;border-left:4px solid #f59e0b;padding:12px 16px;border-radius:6px;margin:20px 0;font-size:13px;'>
              This link expires in <strong>{$expiryMinutes} minutes</strong> and can only be used once.
            </div>

            <p style='color:#6b7280;font-size:13px;'>If you did not request a password reset, please ignore this email. Your password will not change.</p>
            ",
            "Reset your password here: {$resetLink}\nThis link expires in {$expiryMinutes} minutes."
        );

        return $this->send(
            $toEmail, $toName,
            'Reset Your Password — REJUVENATE Digital Health',
            $html,
            "Reset your password: {$resetLink}"
        );
    }

    /* ══════════════════════════════════════════
       3. Password Changed Confirmation
    ══════════════════════════════════════════ */
    public function sendPasswordChanged(string $toEmail, string $toName): bool
    {
        $firstName = explode(' ', trim($toName))[0] ?: 'User';
        $time      = date('d M Y, h:i A');
        $loginUrl  = APP_SITE_URL . 'login.php';

        $html = $this->layout(
            'Password Changed Successfully',
            'Your account password has been updated',
            "
            <p>Hello <strong>{$firstName}</strong>,</p>
            <p>Your password was successfully changed on <strong>{$time}</strong>.</p>

            <div style='text-align:center;margin:24px 0;'>
              <a href='{$loginUrl}' style='background:#00875a;color:#fff;text-decoration:none;
                 padding:13px 32px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;'>
                Login to Your Account
              </a>
            </div>

            <div style='background:#fee2e2;border-left:4px solid #ef4444;padding:12px 16px;border-radius:6px;margin:20px 0;font-size:13px;'>
              <strong>Not you?</strong> If you did not make this change, please
              <a href='mailto:" . MAIL_REPLY_TO . "' style='color:#dc2626;font-weight:600;'>contact support immediately</a>.
            </div>
            ",
            "Your REJUVENATE Digital Health password was changed on {$time}. If this was not you, contact support immediately."
        );

        return $this->send(
            $toEmail, $toName,
            'Your Password Was Changed — REJUVENATE Digital Health',
            $html,
            "Your password was changed on {$time}. If this was not you, contact support immediately."
        );
    }

    /* ══════════════════════════════════════════
       4. Welcome / Registration Email
       $role = 'patient' | 'student' | 'teacher' | 'school_admin' | 'doctor'
    ══════════════════════════════════════════ */
    public function sendWelcome(string $toEmail, string $toName, string $role = 'patient', array $extra = []): bool
    {
        $firstName  = explode(' ', trim($toName))[0] ?: 'User';
        $loginUrl   = APP_SITE_URL . 'login.php';

        $roleLabel = [
            'patient'      => 'Patient',
            'student'      => 'Student',
            'teacher'      => 'Teacher / Staff',
            'school_admin' => 'School Administrator',
            'doctor'       => 'Doctor',
        ][$role] ?? 'Member';

        $schoolLine = !empty($extra['school_name'])
            ? "<p><strong>School:</strong> " . htmlspecialchars($extra['school_name']) . "</p>"
            : '';

        $html = $this->layout(
            "Welcome to REJUVENATE Digital Health",
            "Your account has been created",
            "
            <p>Hello <strong>{$firstName}</strong>,</p>
            <p>Welcome! Your <strong>{$roleLabel}</strong> account on REJUVENATE Digital Health has been created successfully.</p>

            {$schoolLine}

            <div style='background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:13px;'>
              <strong>Account registered as:</strong> {$roleLabel}<br>
              <strong>Email:</strong> {$toEmail}
            </div>

            <div style='text-align:center;margin:24px 0;'>
              <a href='{$loginUrl}' style='background:#0C74C5;color:#fff;text-decoration:none;
                 padding:13px 32px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;'>
                Sign In Now
              </a>
            </div>

            <p style='color:#6b7280;font-size:13px;'>If you did not create this account, please contact our support team.</p>
            ",
            "Welcome to REJUVENATE Digital Health! Your {$roleLabel} account is ready. Login at: {$loginUrl}"
        );

        return $this->send(
            $toEmail, $toName,
            "Welcome to REJUVENATE Digital Health — {$roleLabel} Account Created",
            $html,
            "Welcome! Your {$roleLabel} account is ready. Login at: {$loginUrl}"
        );
    }

    /* ══════════════════════════════════════════
       5. Doctor Account Verified (admin action)
    ══════════════════════════════════════════ */
    public function sendDoctorVerified(string $toEmail, string $toName, string $verifiedBy = 'Administrator'): bool
    {
        $loginUrl    = APP_SITE_URL . 'login.php';
        $currentDate = date('d F Y');

        $html = $this->layout(
            'Account Verified — Welcome Doctor',
            'Your doctor account has been approved',
            "
            <p>Hello <strong>Dr. " . htmlspecialchars($toName) . "</strong>,</p>
            <p>Great news! Your doctor account has been <strong>verified and approved</strong> by our administration team.</p>

            <div style='background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:13px;'>
              <strong>Status:</strong> <span style='color:#00875a;font-weight:700;'>✔ Verified &amp; Active</span><br>
              <strong>Approved on:</strong> {$currentDate}<br>
              <strong>Approved by:</strong> " . htmlspecialchars($verifiedBy) . "
            </div>

            <p>You now have full access to the doctor panel:</p>
            <ul style='line-height:2;color:#374151;font-size:14px;'>
              <li>Manage your profile &amp; availability</li>
              <li>View and accept patient appointments</li>
              <li>Access patient medical records securely</li>
              <li>Generate digital prescriptions</li>
            </ul>

            <div style='text-align:center;margin:24px 0;'>
              <a href='{$loginUrl}' style='background:#00875a;color:#fff;text-decoration:none;
                 padding:13px 32px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;'>
                Login to Doctor Dashboard
              </a>
            </div>

            <div style='background:#fff8e1;border-left:4px solid #f59e0b;padding:12px 16px;border-radius:6px;font-size:13px;'>
              Complete your profile and set your consultation hours for better patient visibility.
            </div>
            ",
            "Dear Dr. {$toName}, your REJUVENATE Digital Health doctor account is now verified and active. Login: {$loginUrl}"
        );

        return $this->send(
            $toEmail, "Dr. {$toName}",
            'Account Verified — REJUVENATE Digital Health',
            $html,
            "Dr. {$toName}, your doctor account is verified and active. Login at: {$loginUrl}"
        );
    }

    /* ══════════════════════════════════════════
       6. Appointment Confirmation
    ══════════════════════════════════════════ */
    public function sendAppointmentConfirmed(string $toEmail, string $toName, array $appt): bool
    {
        $firstName  = explode(' ', trim($toName))[0] ?: 'Patient';
        $dashUrl    = APP_SITE_URL . 'user/user-dashboard.php';

        $html = $this->layout(
            'Appointment Confirmed',
            'Your appointment has been booked',
            "
            <p>Hello <strong>{$firstName}</strong>,</p>
            <p>Your appointment has been confirmed. Here are the details:</p>

            <div style='background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:14px;line-height:2;'>
              <strong>Doctor:</strong> " . htmlspecialchars($appt['doctor_name'] ?? 'N/A') . "<br>
              <strong>Date:</strong> "   . htmlspecialchars($appt['date']        ?? 'N/A') . "<br>
              <strong>Time:</strong> "   . htmlspecialchars($appt['time']        ?? 'N/A') . "<br>
              <strong>Type:</strong> "   . htmlspecialchars($appt['type']        ?? 'Consultation') . "
            </div>

            <div style='text-align:center;margin:24px 0;'>
              <a href='{$dashUrl}' style='background:#0C74C5;color:#fff;text-decoration:none;
                 padding:13px 32px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;'>
                View My Appointments
              </a>
            </div>

            <p style='color:#6b7280;font-size:13px;'>To reschedule or cancel, please visit your dashboard or contact support.</p>
            ",
            "Appointment confirmed with Dr. " . ($appt['doctor_name'] ?? '') . " on " . ($appt['date'] ?? '') . " at " . ($appt['time'] ?? '') . "."
        );

        return $this->send(
            $toEmail, $toName,
            'Appointment Confirmed — REJUVENATE Digital Health',
            $html,
            "Your appointment with Dr. " . ($appt['doctor_name'] ?? '') . " is confirmed on " . ($appt['date'] ?? '')
        );
    }

    /* ══════════════════════════════════════════
       6b. Appointment Request Received (patient) — sent right after
       booking, while the appointment is still 'pending' doctor approval.
       $appt keys: doctor_name, date, time, type, meeting_link (optional),
       account_created, login_email, temp_password (optional — set when
       find_or_create_patient_user() just created a fresh account for them)
    ══════════════════════════════════════════ */
    public function sendAppointmentRequested(string $toEmail, string $toName, array $appt): bool
    {
        $firstName = explode(' ', trim($toName))[0] ?: 'Patient';
        $dashUrl   = APP_SITE_URL . 'user/my-doctor-appointments.php';
        $loginUrl  = APP_SITE_URL . 'login.php';

        $accountBlock = '';
        if (!empty($appt['account_created']) && !empty($appt['temp_password'])) {
            $accountBlock = "
            <div style='background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:13px;'>
              <strong style='color:#166534;'>We've created an account for you</strong>
              <p style='margin:8px 0;'>So you can track this appointment and join your video call from a dashboard, we've set up a REJUVENATE Digital Health account using this email.</p>
              <p style='margin:8px 0;'><strong>Login Email:</strong> " . htmlspecialchars($appt['login_email'] ?? $toEmail) . "<br>
              <strong>Temporary Password:</strong> <code style='background:#fff;padding:2px 8px;border-radius:4px;font-size:14px;border:1px solid #d1d5db;'>" . htmlspecialchars($appt['temp_password']) . "</code></p>
              <p style='margin:8px 0 0;font-size:12px;color:#4b5563;'>Please <a href='{$loginUrl}' style='color:#166534;font-weight:600;'>log in</a> and change your password as soon as possible.</p>
            </div>";
        }

        $meetingBlock = '';
        if (!empty($appt['meeting_link'])) {
            $meetingBlock = "
            <div style='background:#f0fffe;border:1px solid #99f6e4;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:13px;'>
              <strong style='color:#0f766e;'>Video Consultation Link</strong>
              <p style='margin:8px 0;'>This is a one-time link tied to your account — clicking it will ask you to log in if you aren't already.</p>
              <div style='text-align:center;margin:14px 0 4px;'>
                <a href='" . htmlspecialchars($appt['meeting_link']) . "' style='background:#02c9b8;color:#06281f;text-decoration:none;
                   padding:11px 28px;border-radius:8px;font-weight:700;font-size:14px;display:inline-block;'>
                  Join Video Call
                </a>
              </div>
              <p style='margin:8px 0 0;font-size:12px;color:#6b7280;'>This link becomes active once your doctor confirms the appointment. You can also join anytime from <strong>My Appointments</strong> in your dashboard.</p>
            </div>";
        }

        $html = $this->layout(
            'Appointment Request Received',
            'We\'ve received your booking request',
            "
            <p>Hello <strong>{$firstName}</strong>,</p>
            <p>Thanks for booking with REJUVENATE Digital Health. Here's what you requested:</p>

            {$accountBlock}

            <div style='background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:14px;line-height:2;'>
              <strong>Doctor:</strong> " . htmlspecialchars($appt['doctor_name'] ?? 'To be assigned') . "<br>
              <strong>Date:</strong> "   . htmlspecialchars($appt['date']        ?? 'N/A') . "<br>
              <strong>Time:</strong> "   . htmlspecialchars($appt['time']        ?? 'N/A') . "<br>
              <strong>Type:</strong> "   . htmlspecialchars($appt['type']        ?? 'Consultation') . "
            </div>

            {$meetingBlock}

            <div style='background:#fff8e1;border-left:4px solid #f59e0b;padding:12px 16px;border-radius:6px;margin:20px 0;font-size:13px;'>
              Your appointment is <strong>pending confirmation</strong> from the doctor. We'll notify you as soon as it's approved.
            </div>

            <div style='text-align:center;margin:24px 0;'>
              <a href='{$dashUrl}' style='background:#0C74C5;color:#fff;text-decoration:none;
                 padding:13px 32px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;'>
                View My Appointments
              </a>
            </div>
            ",
            "We've received your appointment request with " . ($appt['doctor_name'] ?? 'a doctor') . " on " . ($appt['date'] ?? '') . " at " . ($appt['time'] ?? '') . ". It's pending confirmation."
        );

        return $this->send(
            $toEmail, $toName,
            'Appointment Request Received — REJUVENATE Digital Health',
            $html,
            "Your appointment request with " . ($appt['doctor_name'] ?? '') . " on " . ($appt['date'] ?? '') . " at " . ($appt['time'] ?? '') . " is pending confirmation."
        );
    }

    /* ══════════════════════════════════════════
       6c. New Appointment Request (doctor) — sent right after booking,
       asking the doctor to review/approve.
       $appt keys: patient_name, date, time, type, purpose, meeting_link (optional)
    ══════════════════════════════════════════ */
    public function sendNewAppointmentDoctor(string $toEmail, string $toName, array $appt): bool
    {
        $dashUrl = APP_SITE_URL . 'doctor/appointments.php';

        $meetingBlock = '';
        if (!empty($appt['meeting_link'])) {
            $meetingBlock = "
            <div style='background:#f0fffe;border:1px solid #99f6e4;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:13px;'>
              <strong style='color:#0f766e;'>Video Consultation Link</strong>
              <p style='margin:8px 0;'>This link is tied to your doctor account — you'll be asked to log in if you aren't already.</p>
              <div style='text-align:center;margin:14px 0 4px;'>
                <a href='" . htmlspecialchars($appt['meeting_link']) . "' style='background:#02c9b8;color:#06281f;text-decoration:none;
                   padding:11px 28px;border-radius:8px;font-weight:700;font-size:14px;display:inline-block;'>
                  Join Video Call
                </a>
              </div>
              <p style='margin:8px 0 0;font-size:12px;color:#6b7280;'>You can also join anytime from your <strong>Appointments</strong> dashboard once you approve the request.</p>
            </div>";
        }

        $purposeLine = !empty($appt['purpose'])
            ? "<strong>Purpose:</strong> " . htmlspecialchars($appt['purpose']) . "<br>"
            : '';

        $html = $this->layout(
            'New Appointment Request',
            'A patient has requested a consultation',
            "
            <p>Hello <strong>Dr. " . htmlspecialchars($toName) . "</strong>,</p>
            <p>You have a new appointment request awaiting your approval:</p>

            <div style='background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:14px;line-height:2;'>
              <strong>Patient:</strong> " . htmlspecialchars($appt['patient_name'] ?? 'N/A') . "<br>
              <strong>Date:</strong> "    . htmlspecialchars($appt['date']         ?? 'N/A') . "<br>
              <strong>Time:</strong> "    . htmlspecialchars($appt['time']         ?? 'N/A') . "<br>
              <strong>Type:</strong> "    . htmlspecialchars($appt['type']         ?? 'Consultation') . "<br>
              {$purposeLine}
            </div>

            {$meetingBlock}

            <div style='text-align:center;margin:24px 0;'>
              <a href='{$dashUrl}' style='background:#0C74C5;color:#fff;text-decoration:none;
                 padding:13px 32px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;'>
                Review &amp; Approve
              </a>
            </div>
            ",
            "New appointment request from " . ($appt['patient_name'] ?? '') . " on " . ($appt['date'] ?? '') . " at " . ($appt['time'] ?? '') . ". Please review and approve."
        );

        return $this->send(
            $toEmail, "Dr. {$toName}",
            'New Appointment Request — REJUVENATE Digital Health',
            $html,
            "New appointment request from " . ($appt['patient_name'] ?? '') . " on " . ($appt['date'] ?? '') . " at " . ($appt['time'] ?? '') . "."
        );
    }

    /* ══════════════════════════════════════════
       7. School Member Account Created (by admin/school admin)
    ══════════════════════════════════════════ */
    public function sendSchoolAccountCreated(string $toEmail, string $toName, string $role, string $schoolName, string $uid, string $tempPassword = ''): bool
    {
        $loginUrl  = APP_SITE_URL . 'login.php';
        $firstName = explode(' ', trim($toName))[0] ?: 'User';
        $roleLabel = ucfirst($role);

        $pwLine = $tempPassword
            ? "<p><strong>Temporary Password:</strong> <code style='background:#f3f4f6;padding:2px 8px;border-radius:4px;font-size:14px;'>{$tempPassword}</code></p>
               <p style='font-size:12px;color:#dc2626;'>Please change your password after first login.</p>"
            : '';

        $html = $this->layout(
            "{$roleLabel} Account Created",
            "Your school account is ready",
            "
            <p>Hello <strong>{$firstName}</strong>,</p>
            <p>Your <strong>{$roleLabel}</strong> account at <strong>" . htmlspecialchars($schoolName) . "</strong> has been created on REJUVENATE Digital Health.</p>

            <div style='background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:14px;line-height:2;'>
              <strong>Role:</strong> {$roleLabel}<br>
              <strong>School:</strong> " . htmlspecialchars($schoolName) . "<br>
              <strong>Your ID:</strong> <code>" . htmlspecialchars($uid) . "</code><br>
              <strong>Login Email:</strong> {$toEmail}
            </div>

            {$pwLine}

            <div style='text-align:center;margin:24px 0;'>
              <a href='{$loginUrl}' style='background:#0C74C5;color:#fff;text-decoration:none;
                 padding:13px 32px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;'>
                Login Now
              </a>
            </div>
            ",
            "Your {$roleLabel} account at {$schoolName} is ready. Login ID: {$uid}. Login at: {$loginUrl}"
        );

        return $this->send(
            $toEmail, $toName,
            "Your {$roleLabel} Account — REJUVENATE Digital Health",
            $html,
            "Your {$roleLabel} account at {$schoolName} is ready. Login at: {$loginUrl}"
        );
    }

    /* ══════════════════════════════════════════
       8. General / Custom Email
       Use this when none of the above fit.
    ══════════════════════════════════════════ */
    public function sendCustom(string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText = ''): bool
    {
        $html = $this->layout($subject, '', $bodyHtml, $bodyText);
        return $this->send($toEmail, $toName, $subject, $html, $bodyText ?: strip_tags($bodyHtml));
    }

    /* ─────────────────────────────────────────
       Shared HTML wrapper / layout
    ───────────────────────────────────────── */
    private function layout(string $title, string $subtitle, string $body, string $plainText = ''): string
    {
        $year    = date('Y');
        $siteUrl = APP_SITE_URL;
        $sub     = $subtitle ? "<p style='margin:6px 0 0;font-size:13px;opacity:.85;'>{$subtitle}</p>" : '';

        return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:\'Segoe UI\',Arial,sans-serif;color:#1f2937;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
  <tr><td align="center">
  <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">

    <!-- Header -->
    <tr>
      <td style="background:linear-gradient(135deg,#0C74C5 0%,#02c9b8 100%);padding:32px 36px;text-align:center;">
        <h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;letter-spacing:-.3px;">
          REJUVENATE Digital Health
        </h1>
        <h2 style="margin:12px 0 0;color:#fff;font-size:17px;font-weight:600;">' . htmlspecialchars($title) . '</h2>
        ' . $sub . '
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:32px 36px;font-size:15px;line-height:1.7;color:#374151;">
        ' . $body . '
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="background:#f9fafb;padding:20px 36px;text-align:center;border-top:1px solid #e5e7eb;">
        <p style="margin:0 0 6px;font-size:12px;color:#9ca3af;">
          &copy; ' . $year . ' REJUVENATE Digital Health. All rights reserved.
        </p>
        <p style="margin:0;font-size:11px;color:#9ca3af;">
          <a href="' . $siteUrl . 'privacy-policy" style="color:#0C74C5;text-decoration:none;">Privacy Policy</a>
          &nbsp;|&nbsp;
          <a href="' . $siteUrl . 'terms" style="color:#0C74C5;text-decoration:none;">Terms of Service</a>
          &nbsp;|&nbsp;
          <a href="mailto:' . MAIL_REPLY_TO . '" style="color:#0C74C5;text-decoration:none;">Contact Support</a>
        </p>
        <p style="margin:8px 0 0;font-size:11px;color:#d1d5db;">
          This is an automated email. Please do not reply directly to this message.
        </p>
      </td>
    </tr>

  </table>
  </td></tr>
</table>
</body>
</html>';
    }
}

/* ═══════════════════════════════════════════════════════════════
   Standalone helpers — drop-in replacements for the old
   send_otp_email(), etc. scattered across the codebase.
═══════════════════════════════════════════════════════════════ */

/**
 * Send login / signup / reset OTP.
 * $context: 'login' | 'signup' | 'reset' | 'abha' | 'change_email'
 */
function send_otp_email(string $email, string $otp, string $name = '', string $context = 'login'): bool
{
    return (new Mailer())->sendOtp($email, $name ?: $email, $otp, $context);
}

/** Send forgot-password reset link. */
function send_password_reset_email(string $email, string $name, string $resetLink, int $expiryMinutes = 30): bool
{
    return (new Mailer())->sendPasswordReset($email, $name, $resetLink, $expiryMinutes);
}

/** Notify user their password was changed. */
function send_password_changed_email(string $email, string $name): bool
{
    return (new Mailer())->sendPasswordChanged($email, $name);
}

/** Send welcome email after registration. */
function send_welcome_email(string $email, string $name, string $role = 'patient', array $extra = []): bool
{
    return (new Mailer())->sendWelcome($email, $name, $role, $extra);
}

/** Notify doctor their account was verified by admin. */
function send_doctor_verified_email(string $email, string $name, string $verifiedBy = 'Administrator'): bool
{
    return (new Mailer())->sendDoctorVerified($email, $name, $verifiedBy);
}

/** Appointment confirmed notification to patient. */
function send_appointment_confirmed_email(string $email, string $name, array $apptDetails): bool
{
    return (new Mailer())->sendAppointmentConfirmed($email, $name, $apptDetails);
}

/** School member account created (student/teacher/staff). */
function send_school_account_email(string $email, string $name, string $role, string $schoolName, string $uid, string $tempPassword = ''): bool
{
    return (new Mailer())->sendSchoolAccountCreated($email, $name, $role, $schoolName, $uid, $tempPassword);
}

/**
 * Backward-compat alias used by admin/mail_config.php callers.
 * Sends doctor verification email given a DB connection, doctor ID, and admin ID.
 */
function sendDoctorVerificationEmail(mysqli $conn, int $doctorId, int $adminId): bool
{
    $s = $conn->prepare(
        "SELECT d.email, d.name, a.username AS admin_name
         FROM doctors d LEFT JOIN admin_user a ON a.id = ?
         WHERE d.id = ?"
    );
    $s->bind_param('ii', $adminId, $doctorId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if (!$row) return false;

    return send_doctor_verified_email(
        $row['email'],
        $row['name'],
        $row['admin_name'] ?: 'Administrator'
    );
}

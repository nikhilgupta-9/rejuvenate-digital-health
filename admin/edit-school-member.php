<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: schools-list.php"); exit(); }

$stmt = $conn->prepare("SELECT m.*, s.school_name, s.school_uid FROM school_members m JOIN schools s ON s.id = m.school_id WHERE m.id=?");
$stmt->bind_param('i', $id); $stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) { header("Location: schools-list.php"); exit(); }

$error = '';
$f = $member;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $f = array_merge($member, $_POST);

        $type       = $_POST['type'] ?? '';
        $name       = trim($_POST['name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $dob        = trim($_POST['dob'] ?? '');
        $gender     = $_POST['gender'] ?? '';
        $blood_group = trim($_POST['blood_group'] ?? '');
        $aadhar_last4 = trim($_POST['aadhar_last4'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $class      = trim($_POST['class'] ?? '');
        $section    = trim($_POST['section'] ?? '');
        $roll_number = trim($_POST['roll_number'] ?? '');
        $admission_number = trim($_POST['admission_number'] ?? '');
        $employee_id = trim($_POST['employee_id'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $assigned_class = trim($_POST['assigned_class'] ?? '');
        $status     = $_POST['status'] ?? 'Active';
        $password   = $_POST['password'] ?? '';

        if (!in_array($type, ['Teacher','Student','Staff'])) throw new Exception("Please select a member type.");
        if (!$name) throw new Exception("Name is required.");
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Enter a valid email address.");
        if ($phone && !preg_match('/^[6-9]\d{9}$/', $phone)) throw new Exception("Enter a valid 10-digit phone number.");
        if ($aadhar_last4 && !preg_match('/^\d{4}$/', $aadhar_last4)) throw new Exception("Enter only the last 4 digits of Aadhaar.");
        if (!in_array($gender, ['Male','Female','Other',''])) throw new Exception("Invalid gender.");
        if (!in_array($status, ['Active','Inactive','Pending'])) throw new Exception("Invalid status.");
        if ($password && strlen($password) < 6) throw new Exception("Password must be at least 6 characters.");

        if ($email) {
            $chk = $conn->prepare("SELECT id FROM school_members WHERE email=? AND school_id=? AND id<>?");
            $chk->bind_param('sii', $email, $member['school_id'], $id); $chk->execute();
            if ($chk->get_result()->num_rows > 0) throw new Exception("Another member with this email already exists in this school.");
        }

        $profile_pic = $member['profile_pic'];
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) throw new Exception("Photo must be JPG, PNG or WebP.");
            if ($_FILES['profile_pic']['size'] > 2 * 1024 * 1024) throw new Exception("Photo must be under 2 MB.");
            $upload_dir = '../uploads/schools/members/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'member_' . $id . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $filename)) throw new Exception("Photo upload failed.");
            if ($profile_pic && file_exists('../' . $profile_pic)) @unlink('../' . $profile_pic);
            $profile_pic = 'uploads/schools/members/' . $filename;
        }

        $dob_val = $dob ?: null;

        if ($password) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $upd = $conn->prepare("UPDATE school_members SET
                type=?, name=?, email=?, phone=?, dob=?, gender=?, blood_group=?, aadhar_number=?, address=?, profile_pic=?,
                class=?, section=?, roll_number=?, admission_number=?, employee_id=?, designation=?, assigned_class=?, status=?, password=?
                WHERE id=?");
            $upd->bind_param('sssssssssssssssssssi',
                $type, $name, $email, $phone, $dob_val, $gender, $blood_group, $aadhar_last4, $address, $profile_pic,
                $class, $section, $roll_number, $admission_number, $employee_id, $designation, $assigned_class, $status, $password_hash, $id
            );
        } else {
            $upd = $conn->prepare("UPDATE school_members SET
                type=?, name=?, email=?, phone=?, dob=?, gender=?, blood_group=?, aadhar_number=?, address=?, profile_pic=?,
                class=?, section=?, roll_number=?, admission_number=?, employee_id=?, designation=?, assigned_class=?, status=?
                WHERE id=?");
            $upd->bind_param('ssssssssssssssssssi',
                $type, $name, $email, $phone, $dob_val, $gender, $blood_group, $aadhar_last4, $address, $profile_pic,
                $class, $section, $roll_number, $admission_number, $employee_id, $designation, $assigned_class, $status, $id
            );
        }
        if (!$upd->execute()) throw new Exception("Update failed: " . $conn->error);

        $_SESSION['success_message'] = "Member \"$name\" updated successfully.";
        header("Location: school-view.php?id=" . $member['school_id'] . "&tab=members");
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | Edit School Member</title>
    <?php include "links.php"; ?>
    <style>
        .doctor-form { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.05); padding: 30px; }
        .form-label { font-weight: 500; color: #495057; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 6px; padding: 10px 15px; border: 1px solid #e0e0e0; }
        .form-control:focus, .form-select:focus { border-color: #0C74C5; box-shadow: 0 0 0 3px rgba(12,116,197,.15); }
        .section-title { border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 20px; color: #495057; font-weight: 600; }
        .type-pick { border: 2px solid #e5e7eb; border-radius: 10px; padding: 14px; text-align: center; cursor: pointer; transition: .15s; }
        .type-pick.active { border-color: #0C74C5; background: #eaf4fd; }
        .type-pick i { font-size: 1.4rem; display: block; margin-bottom: 6px; }
    </style>
</head>
<body class="crm_body_bg">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0">
            <div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div>
        </div>
        <div class="main_content_iner">
            <div class="container-fluid">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="page-header mb-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <h2 class="mb-0">Edit Member — <?= htmlspecialchars($member['name']) ?></h2>
                                <a href="school-view.php?id=<?= $member['school_id'] ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to <?= htmlspecialchars($member['school_name']) ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="doctor-form">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="post" enctype="multipart/form-data" id="memberForm">

                                <div class="row mb-4">
                                    <div class="col-12"><h4 class="section-title">School &amp; Member Type</h4></div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($member['school_name']) ?> (<?= htmlspecialchars($member['school_uid']) ?>)" disabled>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <?php foreach (['Active','Inactive','Pending'] as $st): ?>
                                                <option value="<?= $st ?>" <?= $f['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-12 mb-2">
                                        <label class="form-label d-block">Member Type <span class="text-danger">*</span></label>
                                        <input type="hidden" name="type" id="typeInput" value="<?= htmlspecialchars($f['type']) ?>">
                                        <div class="row g-2">
                                            <div class="col-4">
                                                <div class="type-pick" data-type="Student" onclick="pickType('Student')">
                                                    <i class="fas fa-user-graduate" style="color:#0277bd;"></i> Student
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="type-pick" data-type="Teacher" onclick="pickType('Teacher')">
                                                    <i class="fas fa-chalkboard-teacher" style="color:#2e7d32;"></i> Teacher
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="type-pick" data-type="Staff" onclick="pickType('Staff')">
                                                    <i class="fas fa-user-tie" style="color:#6a1b9a;"></i> Staff
                                                </div>
                                            </div>
                                        </div>
                                        <small class="text-muted">Note: the member ID prefix was set at creation and won't change if you switch type.</small>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-12"><h4 class="section-title">Personal Details</h4></div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($f['name']) ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($f['email'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" name="phone" maxlength="10" inputmode="numeric" value="<?= htmlspecialchars($f['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="dob" value="<?= htmlspecialchars($f['dob'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender">
                                            <option value="">Select</option>
                                            <?php foreach (['Male','Female','Other'] as $g): ?>
                                                <option value="<?= $g ?>" <?= ($f['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Blood Group</label>
                                        <input type="text" class="form-control" name="blood_group" value="<?= htmlspecialchars($f['blood_group'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Aadhaar (last 4 digits only)</label>
                                        <input type="text" class="form-control" name="aadhar_last4" maxlength="4" inputmode="numeric" value="<?= htmlspecialchars($f['aadhar_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label d-block">Photo</label>
                                        <?php if ($member['profile_pic']): ?>
                                            <img src="../<?= htmlspecialchars($member['profile_pic']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;" class="mb-2 d-block">
                                        <?php endif; ?>
                                        <input type="file" name="profile_pic" accept="image/*" class="form-control">
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($f['address'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="row mb-4" id="studentFields">
                                    <div class="col-12"><h4 class="section-title">Student Details</h4></div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Class</label>
                                        <input type="text" class="form-control" name="class" value="<?= htmlspecialchars($f['class'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Section</label>
                                        <input type="text" class="form-control" name="section" value="<?= htmlspecialchars($f['section'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Roll Number</label>
                                        <input type="text" class="form-control" name="roll_number" value="<?= htmlspecialchars($f['roll_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Admission Number</label>
                                        <input type="text" class="form-control" name="admission_number" value="<?= htmlspecialchars($f['admission_number'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="row mb-4" id="staffFields">
                                    <div class="col-12"><h4 class="section-title">Employment Details</h4></div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Employee ID</label>
                                        <input type="text" class="form-control" name="employee_id" value="<?= htmlspecialchars($f['employee_id'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Designation</label>
                                        <input type="text" class="form-control" name="designation" value="<?= htmlspecialchars($f['designation'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4 mb-3" id="assignedClassField">
                                        <label class="form-label">Assigned Class</label>
                                        <input type="text" class="form-control" name="assigned_class" value="<?= htmlspecialchars($f['assigned_class'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-12"><h4 class="section-title">Login Access</h4></div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Reset Password</label>
                                        <input type="password" class="form-control" name="password" minlength="6" placeholder="Leave blank to keep current password">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mt-2">
                                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-save me-2"></i> Save Changes</button>
                                        <a href="school-view.php?id=<?= $member['school_id'] ?>" class="btn btn-outline-secondary"><i class="fas fa-times me-2"></i> Cancel</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include "footer.php"; ?>
    </section>
    <script>
        function pickType(type) {
            document.getElementById('typeInput').value = type;
            document.querySelectorAll('.type-pick').forEach(el => el.classList.toggle('active', el.dataset.type === type));
            document.getElementById('studentFields').style.display = type === 'Student' ? 'flex' : 'none';
            document.getElementById('staffFields').style.display = (type === 'Teacher' || type === 'Staff') ? 'flex' : 'none';
            document.getElementById('assignedClassField').style.display = type === 'Teacher' ? 'block' : 'none';
        }
        pickType(document.getElementById('typeInput').value || 'Student');
    </script>
</body>
</html>

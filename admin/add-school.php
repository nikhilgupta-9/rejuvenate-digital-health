<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$admin_id = $_SESSION['admin_id'] ?? 1;
$error = '';
$old = $_POST;

$states = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana',
           'Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur',
           'Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana',
           'Tripura','Uttar Pradesh','Uttarakhand','West Bengal',
           'Delhi (NCT)','Jammu & Kashmir','Ladakh','Puducherry','Chandigarh','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $school_name         = trim($_POST['school_name'] ?? '');
        $email                = trim($_POST['email'] ?? '');
        $phone                = trim($_POST['phone'] ?? '');
        $address              = trim($_POST['address'] ?? '');
        $city                 = trim($_POST['city'] ?? '');
        $state                = trim($_POST['state'] ?? '');
        $pincode              = trim($_POST['pincode'] ?? '');
        $board                = $_POST['board'] ?? 'CBSE';
        $school_type          = $_POST['school_type'] ?? 'Private';
        $principal_name       = trim($_POST['principal_name'] ?? '');
        $registration_number  = trim($_POST['registration_number'] ?? '');
        $status               = $_POST['status'] ?? 'Active';

        $admin_name     = trim($_POST['admin_name'] ?? '');
        $admin_email    = trim($_POST['admin_email'] ?? '');
        $admin_phone    = trim($_POST['admin_phone'] ?? '');
        $password       = $_POST['password'] ?? '';
        $confirm        = $_POST['confirm_password'] ?? '';

        if (!$school_name) throw new Exception("School name is required.");
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Enter a valid school email address.");
        if (!preg_match('/^[6-9]\d{9}$/', $phone)) throw new Exception("Enter a valid 10-digit school phone number.");
        if (!$address) throw new Exception("Address is required.");
        if (!$city) throw new Exception("City is required.");
        if (!$state) throw new Exception("State is required.");
        if (!preg_match('/^\d{6}$/', $pincode)) throw new Exception("Enter a valid 6-digit pincode.");
        if (!in_array($board, ['CBSE','ICSE','State Board','Other'])) throw new Exception("Invalid board.");
        if (!in_array($school_type, ['Government','Private','Semi-Government'])) throw new Exception("Invalid school type.");
        if (!$principal_name) throw new Exception("Principal name is required.");
        if (!in_array($status, ['Pending','Active','Inactive'])) throw new Exception("Invalid status.");

        if (!$admin_name) throw new Exception("School admin name is required.");
        if (!$admin_email || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) throw new Exception("Enter a valid school admin email address.");
        if (!preg_match('/^[6-9]\d{9}$/', $admin_phone)) throw new Exception("Enter a valid 10-digit school admin phone number.");
        if (strlen($password) < 8) throw new Exception("Password must be at least 8 characters.");
        if ($password !== $confirm) throw new Exception("Passwords do not match.");

        $chk = $conn->prepare("SELECT id FROM schools WHERE email=?");
        $chk->bind_param('s', $email); $chk->execute();
        if ($chk->get_result()->num_rows > 0) throw new Exception("A school with this email already exists.");

        $chk2 = $conn->prepare("SELECT id FROM school_users WHERE email=?");
        $chk2->bind_param('s', $admin_email); $chk2->execute();
        if ($chk2->get_result()->num_rows > 0) throw new Exception("This admin email is already in use.");

        // Logo upload
        $logo_path = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) throw new Exception("Logo must be JPG, PNG or WebP.");
            if ($_FILES['logo']['size'] > 2 * 1024 * 1024) throw new Exception("Logo must be under 2 MB.");
            $upload_dir = '../uploads/schools/logos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'school_' . time() . '_' . mt_rand(100,999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $filename)) throw new Exception("Logo upload failed.");
            $logo_path = 'uploads/schools/logos/' . $filename;
        }

        $conn->begin_transaction();
        try {
            $approved_by = null; $approved_at = null;
            $ins = $conn->prepare("INSERT INTO schools
                (school_name, email, phone, address, city, state, pincode, board, school_type, principal_name, logo, registration_number, status, approved_by, approved_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, " . ($status === 'Active' ? 'NOW()' : 'NULL') . ")");
            $approved_by_val = $status === 'Active' ? $admin_id : null;
            $ins->bind_param('sssssssssssssi',
                $school_name, $email, $phone, $address, $city, $state, $pincode,
                $board, $school_type, $principal_name, $logo_path, $registration_number, $status, $approved_by_val
            );
            if (!$ins->execute()) throw new Exception("Failed to create school: " . $conn->error);
            $school_id = $ins->insert_id;

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $user_status = $status === 'Active' ? 'Active' : 'Inactive';
            $ins2 = $conn->prepare("INSERT INTO school_users (school_id, name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, 'school_admin', ?)");
            $ins2->bind_param('isssss', $school_id, $admin_name, $admin_email, $admin_phone, $hash, $user_status);
            if (!$ins2->execute()) throw new Exception("Failed to create school admin account: " . $conn->error);

            $conn->commit();
            $_SESSION['success_message'] = "School \"$school_name\" added successfully.";
            header("Location: school-view.php?id=$school_id");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
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
    <title>Admin | Add School</title>
    <?php include "links.php"; ?>
    <style>
        .doctor-form { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.05); padding: 30px; }
        .form-label { font-weight: 500; color: #495057; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 6px; padding: 10px 15px; border: 1px solid #e0e0e0; }
        .form-control:focus, .form-select:focus { border-color: #0C74C5; box-shadow: 0 0 0 3px rgba(12,116,197,.15); }
        .section-title { border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 20px; color: #495057; font-weight: 600; }
        .logo-preview { max-width: 120px; max-height: 120px; margin-top: 10px; border-radius: 8px; border: 1px dashed #ddd; padding: 5px; }
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
                                <h2 class="mb-0">Add New School</h2>
                                <a href="schools-list.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to List
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

                            <form method="post" enctype="multipart/form-data" id="schoolForm">

                                <div class="row mb-4">
                                    <div class="col-12"><h4 class="section-title">School Information</h4></div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="school_name" required value="<?= htmlspecialchars($old['school_name'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Principal Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="principal_name" required value="<?= htmlspecialchars($old['principal_name'] ?? '') ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School Phone <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="phone" maxlength="10" inputmode="numeric" required value="<?= htmlspecialchars($old['phone'] ?? '') ?>" placeholder="10-digit mobile">
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Board</label>
                                        <select class="form-select" name="board">
                                            <?php foreach (['CBSE','ICSE','State Board','Other'] as $b): ?>
                                                <option value="<?= $b ?>" <?= ($old['board'] ?? 'CBSE') === $b ? 'selected' : '' ?>><?= $b ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">School Type</label>
                                        <select class="form-select" name="school_type">
                                            <?php foreach (['Government','Private','Semi-Government'] as $t): ?>
                                                <option value="<?= $t ?>" <?= ($old['school_type'] ?? 'Private') === $t ? 'selected' : '' ?>><?= $t ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="Active" <?= ($old['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active (approve immediately)</option>
                                            <option value="Pending" <?= ($old['status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending Approval</option>
                                            <option value="Inactive" <?= ($old['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Registration Number</label>
                                        <input type="text" class="form-control" name="registration_number" value="<?= htmlspecialchars($old['registration_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label d-block">School Logo</label>
                                        <div class="file-upload mb-2">
                                            <input type="file" name="logo" accept="image/*" class="form-control" onchange="previewLogo(this)">
                                        </div>
                                        <img id="logoPreview" class="logo-preview d-none">
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Address <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="address" rows="2" required><?= htmlspecialchars($old['address'] ?? '') ?></textarea>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">City <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="city" required value="<?= htmlspecialchars($old['city'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">State <span class="text-danger">*</span></label>
                                        <select class="form-select" name="state" required>
                                            <option value="">Select</option>
                                            <?php foreach ($states as $s): ?>
                                                <option value="<?= $s ?>" <?= ($old['state'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Pincode <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="pincode" maxlength="6" inputmode="numeric" required value="<?= htmlspecialchars($old['pincode'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-12"><h4 class="section-title">School Admin Account</h4></div>
                                    <div class="col-12 mb-2">
                                        <small class="text-muted">This account will be used by the school to log in to their dashboard.</small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Admin Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="admin_name" required value="<?= htmlspecialchars($old['admin_name'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Admin Phone <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="admin_phone" maxlength="10" inputmode="numeric" required value="<?= htmlspecialchars($old['admin_phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Admin Email (login) <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="admin_email" required value="<?= htmlspecialchars($old['admin_email'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6"></div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="password" required minlength="8" placeholder="At least 8 characters">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="confirm_password" required minlength="8">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mt-2">
                                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-save me-2"></i> Add School</button>
                                        <a href="schools-list.php" class="btn btn-outline-secondary"><i class="fas fa-times me-2"></i> Cancel</a>
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
        function previewLogo(input) {
            const preview = document.getElementById('logoPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => { preview.src = e.target.result; preview.classList.remove('d-none'); };
                reader.readAsDataURL(input.files[0]);
            }
        }
        document.getElementById('schoolForm').addEventListener('submit', function(e) {
            const p1 = this.password.value, p2 = this.confirm_password.value;
            if (p1 !== p2) { e.preventDefault(); alert('Passwords do not match.'); }
        });
    </script>
</body>
</html>

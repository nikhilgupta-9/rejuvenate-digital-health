<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) { header("Location: auth/login.php"); exit(); }
include "db-conn.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: schools-list.php"); exit(); }

$stmt = $conn->prepare("SELECT * FROM schools WHERE id=?");
$stmt->bind_param('i', $id); $stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
if (!$school) { header("Location: schools-list.php"); exit(); }

$error = '';
$states = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana',
           'Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur',
           'Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana',
           'Tripura','Uttar Pradesh','Uttarakhand','West Bengal',
           'Delhi (NCT)','Jammu & Kashmir','Ladakh','Puducherry','Chandigarh','Other'];

$f = $school; // form values, overwritten by POST on validation failure

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $f = array_merge($school, $_POST);

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
        $status               = $_POST['status'] ?? $school['status'];

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
        if (!in_array($status, ['Pending','Active','Inactive','Rejected'])) throw new Exception("Invalid status.");

        $chk = $conn->prepare("SELECT id FROM schools WHERE email=? AND id<>?");
        $chk->bind_param('si', $email, $id); $chk->execute();
        if ($chk->get_result()->num_rows > 0) throw new Exception("Another school is already using this email.");

        $logo_path = $school['logo'];
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) throw new Exception("Logo must be JPG, PNG or WebP.");
            if ($_FILES['logo']['size'] > 2 * 1024 * 1024) throw new Exception("Logo must be under 2 MB.");
            $upload_dir = '../uploads/schools/logos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'school_' . $id . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $filename)) throw new Exception("Logo upload failed.");
            if ($logo_path && file_exists('../' . $logo_path)) @unlink('../' . $logo_path);
            $logo_path = 'uploads/schools/logos/' . $filename;
        }

        $approved_by = $school['approved_by'];
        $approved_at = $school['approved_at'];
        if ($status === 'Active' && $school['status'] !== 'Active') {
            $approved_by = $_SESSION['admin_id'] ?? 1;
            $approved_at = date('Y-m-d H:i:s');
        }

        $upd = $conn->prepare("UPDATE schools SET
            school_name=?, email=?, phone=?, address=?, city=?, state=?, pincode=?,
            board=?, school_type=?, principal_name=?, logo=?, registration_number=?, status=?,
            approved_by=?, approved_at=?
            WHERE id=?");
        $upd->bind_param('sssssssssssssisi',
            $school_name, $email, $phone, $address, $city, $state, $pincode,
            $board, $school_type, $principal_name, $logo_path, $registration_number, $status,
            $approved_by, $approved_at, $id
        );
        if (!$upd->execute()) throw new Exception("Update failed: " . $conn->error);

        $_SESSION['success_message'] = "School updated successfully.";
        header("Location: school-view.php?id=$id");
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
    <title>Admin | Edit School</title>
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
                                <h2 class="mb-0">Edit School — <?= htmlspecialchars($school['school_name']) ?></h2>
                                <a href="school-view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Details
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
                                        <input type="text" class="form-control" name="school_name" required value="<?= htmlspecialchars($f['school_name']) ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Principal Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="principal_name" required value="<?= htmlspecialchars($f['principal_name']) ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($f['email']) ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">School Phone <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="phone" maxlength="10" inputmode="numeric" required value="<?= htmlspecialchars($f['phone']) ?>">
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Board</label>
                                        <select class="form-select" name="board">
                                            <?php foreach (['CBSE','ICSE','State Board','Other'] as $b): ?>
                                                <option value="<?= $b ?>" <?= $f['board'] === $b ? 'selected' : '' ?>><?= $b ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">School Type</label>
                                        <select class="form-select" name="school_type">
                                            <?php foreach (['Government','Private','Semi-Government'] as $t): ?>
                                                <option value="<?= $t ?>" <?= $f['school_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <?php foreach (['Pending'=>'Pending','Active'=>'Active','Inactive'=>'Inactive','Rejected'=>'Rejected'] as $v=>$l): ?>
                                                <option value="<?= $v ?>" <?= $f['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Registration Number</label>
                                        <input type="text" class="form-control" name="registration_number" value="<?= htmlspecialchars($f['registration_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label d-block">School Logo</label>
                                        <?php if ($school['logo']): ?>
                                            <img src="../<?= htmlspecialchars($school['logo']) ?>" class="logo-preview d-block mb-2" id="currentLogo">
                                        <?php endif; ?>
                                        <input type="file" name="logo" accept="image/*" class="form-control" onchange="previewLogo(this)">
                                        <img id="logoPreview" class="logo-preview d-none">
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Address <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="address" rows="2" required><?= htmlspecialchars($f['address']) ?></textarea>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">City <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="city" required value="<?= htmlspecialchars($f['city']) ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">State <span class="text-danger">*</span></label>
                                        <select class="form-select" name="state" required>
                                            <option value="">Select</option>
                                            <?php foreach ($states as $s): ?>
                                                <option value="<?= $s ?>" <?= $f['state'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Pincode <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="pincode" maxlength="6" inputmode="numeric" required value="<?= htmlspecialchars($f['pincode']) ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mt-2">
                                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-save me-2"></i> Save Changes</button>
                                        <a href="school-view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="fas fa-times me-2"></i> Cancel</a>
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
    </script>
</body>
</html>

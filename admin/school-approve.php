<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$id       = intval($_GET['id'] ?? 0);
$action   = $_GET['action'] ?? '';
$admin_id = $_SESSION['admin_id'] ?? 1;

if (!$id || !in_array($action, ['approve','reject','activate','deactivate'])) {
    header("Location: schools-list.php"); exit();
}

$school = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM schools WHERE id=$id"));
if (!$school) { header("Location: schools-list.php"); exit(); }

// POST: rejection with reason
if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = mysqli_real_escape_string($conn, trim($_POST['rejection_reason'] ?? 'No reason provided.'));
    mysqli_query($conn, "UPDATE schools SET status='Rejected', rejection_reason='$reason', approved_by=$admin_id, approved_at=NOW() WHERE id=$id");
    mysqli_query($conn, "UPDATE school_users SET status='Suspended' WHERE school_id=$id");
    header("Location: schools-list.php?msg=rejected"); exit();
}

switch ($action) {
    case 'approve':
        mysqli_query($conn, "UPDATE schools SET status='Active', approved_by=$admin_id, approved_at=NOW(), rejection_reason=NULL WHERE id=$id");
        mysqli_query($conn, "UPDATE school_users SET status='Active' WHERE school_id=$id");
        header("Location: school-view.php?id=$id&msg=approved"); exit();
    case 'activate':
        mysqli_query($conn, "UPDATE schools SET status='Active' WHERE id=$id");
        mysqli_query($conn, "UPDATE school_users SET status='Active' WHERE school_id=$id");
        header("Location: school-view.php?id=$id&msg=activated"); exit();
    case 'deactivate':
        mysqli_query($conn, "UPDATE schools SET status='Inactive' WHERE id=$id");
        mysqli_query($conn, "UPDATE school_users SET status='Inactive' WHERE school_id=$id");
        header("Location: school-view.php?id=$id&msg=deactivated"); exit();
}
// Only 'reject' falls through to form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | Reject School</title>
    <?php include "links.php"; ?>
</head>
<body>
<div class="wrapper">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0">
            <div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div>
        </div>
        <div class="main_content_iner">
            <div class="container-fluid p-0">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="white_card">
                            <div class="white_card_header" style="background:linear-gradient(135deg,#ef233c,#8d0801); border-radius:8px 8px 0 0;">
                                <div class="main-title"><h3 class="m-0 text-white"><i class="fas fa-times-circle me-2"></i>Reject School Registration</h3></div>
                            </div>
                            <div class="white_card_body">
                                <div class="mb-3 p-3 rounded" style="background:#fff8f8; border:1px solid #ffd5d5;">
                                    <strong><?= htmlspecialchars($school['school_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($school['email']) ?> &bull; <?= htmlspecialchars($school['city']) ?>, <?= htmlspecialchars($school['state']) ?></small>
                                </div>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="rejection_reason" rows="4" required placeholder="Explain why this registration is rejected..."></textarea>
                                        <small class="text-muted">This will be shown to the school admin.</small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i>Confirm Reject</button>
                                        <a href="school-view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include "footer.php"; ?>
</body>
</html>

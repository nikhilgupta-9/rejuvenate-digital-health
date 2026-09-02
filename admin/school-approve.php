<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../util/mail_config.php';
admin_jwt_guard();

$id       = intval($_GET['id'] ?? 0);
$action   = $_GET['action'] ?? '';
$admin_id = $_SESSION['admin_id'] ?? 1;

if (!$id || !in_array($action, ['approve','reject','activate','deactivate'])) {
    header("Location: schools-list.php"); exit();
}

$school = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM schools WHERE id=$id"));
if (!$school) { header("Location: schools-list.php"); exit(); }

// School admin account — used for the greeting / recipient name in emails
$school_admin = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT name, email FROM school_users WHERE school_id=$id ORDER BY id ASC LIMIT 1"
));
$notify_email = $school_admin['email'] ?? $school['email'];
$notify_name  = $school_admin['name']  ?? ($school['principal_name'] ?: 'School Admin');

/**
 * Email the school admin about an approval / rejection decision.
 */
function notify_school_decision(string $toEmail, string $toName, array $school, string $decision, string $reason = ''): void
{
    if (!$toEmail) return;
    $loginUrl   = (defined('APP_SITE_URL') ? APP_SITE_URL : 'http://localhost/rejuvenate-digital-health/') . 'school-login.php';
    $schoolName = htmlspecialchars($school['school_name']);
    $schoolUid  = htmlspecialchars($school['school_uid']);

    try {
        $mailer = new Mailer();

        if ($decision === 'approve') {
            $html = "
                <p>Hello <strong>" . htmlspecialchars($toName) . "</strong>,</p>
                <p>Good news — <strong>{$schoolName}</strong> has been <strong>verified and approved</strong> on
                REJUVENATE Digital Health.</p>
                <div style='background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:14px;line-height:2;'>
                  <strong>School UID:</strong> {$schoolUid}<br>
                  <strong>Login Email:</strong> " . htmlspecialchars($school['email']) . "<br>
                  <strong>Status:</strong> <span style='color:#00875a;font-weight:700;'>Active</span>
                </div>
                <p>You can now log in to your school dashboard to add your logo, board, address, and start
                onboarding teachers, students and staff.</p>
                <div style='text-align:center;margin:24px 0;'>
                  <a href='{$loginUrl}' style='background:#00875a;color:#fff;text-decoration:none;padding:13px 32px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;'>
                    Login to School Dashboard
                  </a>
                </div>
            ";
            $text = "Hello {$toName},\n\n{$school['school_name']} has been verified and approved on REJUVENATE Digital Health.\n"
                  . "School UID: {$school['school_uid']}\nLogin Email: {$school['email']}\nStatus: Active\n\n"
                  . "Login: {$loginUrl}";
            $mailer->sendCustom($toEmail, $toName, 'School Approved — ' . $school['school_name'], $html, $text);
        } else { // reject
            $reasonHtml = nl2br(htmlspecialchars($reason ?: 'No reason provided.'));
            $html = "
                <p>Hello <strong>" . htmlspecialchars($toName) . "</strong>,</p>
                <p>We're sorry to inform you that the registration for <strong>{$schoolName}</strong> could not be
                approved at this time.</p>
                <div style='background:#fef2f2;border-left:4px solid #ef4444;padding:12px 16px;border-radius:6px;margin:20px 0;font-size:14px;'>
                  <strong>Reason:</strong><br>{$reasonHtml}
                </div>
                <p>If you believe this is a mistake or can provide the required information, please reply to this
                email or contact our support team.</p>
            ";
            $text = "Hello {$toName},\n\nThe registration for {$school['school_name']} could not be approved at this time.\n\n"
                  . "Reason: " . ($reason ?: 'No reason provided.') . "\n\n"
                  . "If you can provide the required information, please contact our support team.";
            $mailer->sendCustom($toEmail, $toName, 'School Registration Update — ' . $school['school_name'], $html, $text);
        }
    } catch (Exception $e) {
        error_log('[school-approve] notification email failed: ' . $e->getMessage());
    }
}

// POST: rejection with reason
if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason_raw = trim($_POST['rejection_reason'] ?? 'No reason provided.');
    $reason = mysqli_real_escape_string($conn, $reason_raw);
    mysqli_query($conn, "UPDATE schools SET status='Rejected', rejection_reason='$reason', approved_by=$admin_id, approved_at=NOW() WHERE id=$id");
    mysqli_query($conn, "UPDATE school_users SET status='Suspended' WHERE school_id=$id");
    notify_school_decision($notify_email, $notify_name, $school, 'reject', $reason_raw);
    $_SESSION['success_message'] = $school['school_name'] . ' was rejected. The school admin has been notified by email.';
    header("Location: schools-list.php?msg=rejected"); exit();
}

switch ($action) {
    case 'approve':
        mysqli_query($conn, "UPDATE schools SET status='Active', approved_by=$admin_id, approved_at=NOW(), rejection_reason=NULL WHERE id=$id");
        mysqli_query($conn, "UPDATE school_users SET status='Active' WHERE school_id=$id");
        notify_school_decision($notify_email, $notify_name, $school, 'approve');
        $_SESSION['success_message'] = $school['school_name'] . ' has been approved. A notification email was sent to ' . $notify_email . '.';
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
            <div class="container-fluid p-0 sm_padding_15px">
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

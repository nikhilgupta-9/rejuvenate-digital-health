<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../util/mail_config.php';
admin_jwt_guard();

const SCHOOL_REFERRAL_COMMISSION_RATE = 0.25;

$id       = intval($_GET['id'] ?? 0);
$action   = $_GET['action'] ?? '';
$admin_id = $_SESSION['admin_id'] ?? 1;

if (!$id || !in_array($action, ['approve', 'reject'])) {
    header("Location: school-subscriptions.php"); exit();
}

$sub = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT s.*, p.name AS plan_name, p.billing_cycle_days, p.max_students
    FROM school_subscriptions s
    JOIN school_plans p ON p.id = s.plan_id
    WHERE s.id=$id
"));
if (!$sub || $sub['status'] !== 'pending_approval') {
    header("Location: school-subscriptions.php"); exit();
}

$school = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM schools WHERE id=" . (int) $sub['school_id']));
if (!$school) { header("Location: school-subscriptions.php"); exit(); }

$school_admin = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT name, email FROM school_users WHERE school_id=" . (int) $school['id'] . " ORDER BY id ASC LIMIT 1"
));
$notify_email = $school_admin['email'] ?? $school['email'];
$notify_name  = $school_admin['name']  ?? ($school['principal_name'] ?: 'School Admin');

/**
 * Email the school admin about a subscription approval / rejection decision.
 */
function notify_school_subscription_decision(string $toEmail, string $toName, array $school, array $sub, string $decision, string $expiresAt = '', string $reason = ''): void
{
    if (!$toEmail) return;
    $schoolName = htmlspecialchars($school['school_name']);
    $planName   = htmlspecialchars($sub['plan_name']);

    try {
        $mailer = new Mailer();

        if ($decision === 'approve') {
            $dashUrl = (defined('APP_SITE_URL') ? APP_SITE_URL : 'http://localhost/rejuvenate-digital-health/') . 'school/subscription.php';
            $expiryLabel = $expiresAt ? date('d M Y', strtotime($expiresAt)) : 'N/A';
            $html = "
                <p>Hello <strong>" . htmlspecialchars($toName) . "</strong>,</p>
                <p>Your subscription request for <strong>{$schoolName}</strong> has been <strong>approved</strong> on
                REJUVENATE Digital Health.</p>
                <div style='background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 20px;margin:20px 0;font-size:14px;line-height:2;'>
                  <strong>Plan:</strong> {$planName}<br>
                  <strong>Valid until:</strong> {$expiryLabel}<br>
                  <strong>Status:</strong> <span style='color:#00875a;font-weight:700;'>Active</span>
                </div>
                <div style='text-align:center;margin:24px 0;'>
                  <a href='{$dashUrl}' style='background:#00875a;color:#fff;text-decoration:none;padding:13px 32px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;'>
                    View Subscription
                  </a>
                </div>
            ";
            $text = "Hello {$toName},\n\nYour subscription request for {$school['school_name']} has been approved.\n"
                  . "Plan: {$sub['plan_name']}\nValid until: {$expiryLabel}\nStatus: Active";
            $mailer->sendCustom($toEmail, $toName, 'Subscription Approved — ' . $school['school_name'], $html, $text);
        } else { // reject
            $reasonHtml = nl2br(htmlspecialchars($reason ?: 'No reason provided.'));
            $html = "
                <p>Hello <strong>" . htmlspecialchars($toName) . "</strong>,</p>
                <p>We're sorry to inform you that the <strong>{$planName}</strong> subscription request for
                <strong>{$schoolName}</strong> could not be approved at this time.</p>
                <div style='background:#fef2f2;border-left:4px solid #ef4444;padding:12px 16px;border-radius:6px;margin:20px 0;font-size:14px;'>
                  <strong>Reason:</strong><br>{$reasonHtml}
                </div>
                <p>If you believe this is a mistake, please reply to this email or contact our support team.</p>
            ";
            $text = "Hello {$toName},\n\nThe {$sub['plan_name']} subscription request for {$school['school_name']} could not be approved at this time.\n\n"
                  . "Reason: " . ($reason ?: 'No reason provided.');
            $mailer->sendCustom($toEmail, $toName, 'Subscription Request Update — ' . $school['school_name'], $html, $text);
        }
    } catch (Exception $e) {
        error_log('[school-subscription-approve] notification email failed: ' . $e->getMessage());
    }
}

// POST: rejection with reason
if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason_raw = trim($_POST['rejection_reason'] ?? 'No reason provided.');
    $reason = mysqli_real_escape_string($conn, $reason_raw);
    mysqli_query($conn, "UPDATE school_subscriptions SET status='rejected', rejection_reason='$reason', approved_by=$admin_id, approved_at=NOW() WHERE id=$id");
    notify_school_subscription_decision($notify_email, $notify_name, $school, $sub, 'reject', '', $reason_raw);
    $_SESSION['success_message'] = 'Subscription request for ' . $school['school_name'] . ' was rejected. The school has been notified by email.';
    header("Location: school-subscriptions.php?msg=rejected"); exit();
}

if ($action === 'approve') {
    $cycleDays = (int) $sub['billing_cycle_days'] ?: 365;

    $cur = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT MAX(expires_at) AS current_expiry FROM school_subscriptions WHERE school_id=" . (int) $school['id'] . " AND status='active'"
    ));
    $currentExpiry = $cur['current_expiry'] ?? null;
    $startsAt  = ($currentExpiry && strtotime($currentExpiry) > time()) ? $currentExpiry : date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime($startsAt . " +{$cycleDays} days"));

    // First-paid-subscription check for referral commission — must run
    // BEFORE this row is flipped to 'active' below.
    $isFirstPaid = false;
    if (!empty($school['referred_by']) && (float) $sub['amount'] > 0) {
        $priorPaid = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) c FROM school_subscriptions
             WHERE school_id=" . (int) $school['id'] . " AND status='active' AND amount > 0 AND id != $id"
        ));
        $isFirstPaid = ((int) ($priorPaid['c'] ?? 0)) === 0;
    }

    $upd = $conn->prepare("UPDATE school_subscriptions SET status='active', approved_by=?, approved_at=NOW(), starts_at=?, expires_at=? WHERE id=?");
    $upd->bind_param('issi', $admin_id, $startsAt, $expiresAt, $id);
    $upd->execute();

    if ($isFirstPaid) {
        $commission = round(((float) $sub['amount']) * SCHOOL_REFERRAL_COMMISSION_RATE, 2);
        $earnIns = $conn->prepare("INSERT IGNORE INTO school_referral_earnings
            (referring_school_id, referred_school_id, school_subscription_id, subscription_amount, commission_amount)
            VALUES (?, ?, ?, ?, ?)");
        $referringSchoolId = (int) $school['referred_by'];
        $earnIns->bind_param('iiidd', $referringSchoolId, $school['id'], $id, $sub['amount'], $commission);
        $earnIns->execute();
    }

    notify_school_subscription_decision($notify_email, $notify_name, $school, $sub, 'approve', $expiresAt);
    $_SESSION['success_message'] = $sub['plan_name'] . ' subscription for ' . $school['school_name'] . ' has been approved. A notification email was sent to ' . $notify_email . '.';
    header("Location: school-subscriptions.php?msg=approved"); exit();
}
// Only 'reject' falls through to form
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | Reject School Subscription</title>
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
                                <div class="main-title"><h3 class="m-0 text-white"><i class="fas fa-times-circle me-2"></i>Reject Subscription Request</h3></div>
                            </div>
                            <div class="white_card_body">
                                <div class="mb-3 p-3 rounded" style="background:#fff8f8; border:1px solid #ffd5d5;">
                                    <strong><?= htmlspecialchars($school['school_name']) ?></strong> &bull; <?= htmlspecialchars($sub['plan_name']) ?> (&#8377;<?= number_format((float) $sub['amount']) ?>)<br>
                                    <small class="text-muted"><?= htmlspecialchars($school['email']) ?></small>
                                </div>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="rejection_reason" rows="4" required placeholder="Explain why this subscription request is rejected..."></textarea>
                                        <small class="text-muted">This will be shown to the school admin.</small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i>Confirm Reject</button>
                                        <a href="school-subscriptions.php" class="btn btn-outline-secondary">Cancel</a>
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

<?php
/**
 * Admin -> Settings -> Platform Settings
 *
 * Generic admin-editable key/value config, same shape as telemedicine_settings
 * (see database/migration_school_membership_phase2.sql). Currently backs the
 * school-membership commission model: the % the school earns on each paid
 * membership, and how many days a membership can be cancelled/refunded in.
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
$payload = admin_jwt_guard();
$admin_id = (int) ($payload['admin_id'] ?? $payload['sub'] ?? 0);

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_message = '';
$page_message_type = '';

$FIELDS = ['school_commission_percent', 'membership_refund_window_days'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_platform_settings'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $commission = max(0, min(100, (float) ($_POST['school_commission_percent'] ?? 10)));
        $refundDays = max(0, min(90, (int) ($_POST['membership_refund_window_days'] ?? 7)));
        $values = [
            'school_commission_percent'     => (string) $commission,
            'membership_refund_window_days' => (string) $refundDays,
        ];
        $stmt = $conn->prepare("INSERT INTO platform_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
        foreach ($values as $k => $v) {
            $stmt->bind_param('ssi', $k, $v, $admin_id);
            $stmt->execute();
        }
        $_SESSION['success_message'] = 'Platform settings saved.';
        header('Location: platform-settings.php');
        exit;
    }
}

$cfg = [];
$res = $conn->query("SELECT setting_key, setting_value FROM platform_settings");
while ($r = $res->fetch_assoc()) $cfg[$r['setting_key']] = $r['setting_value'];
$get = fn($k, $d = '') => htmlspecialchars($cfg[$k] ?? $d);

/* quick stats — what these settings currently govern */
$stat = fn($sql) => (int) ($conn->query($sql)->fetch_assoc()['c'] ?? 0);
$s_active_members  = $stat("SELECT COUNT(*) c FROM school_health_memberships WHERE status='active'");
$s_commission_held = $stat("SELECT COUNT(*) c FROM school_health_memberships WHERE commission_status='held'");
$sum = $conn->query("SELECT COALESCE(SUM(commission_amount),0) s FROM school_health_memberships WHERE commission_status='held'")->fetch_assoc()['s'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Platform Settings | Admin Panel</title>
    <?php include "links.php"; ?>
</head>
<body class="crm_body_bg">
<?php include "header.php"; ?>

<section class="main_content dashboard_part large_header_bg">
    <div class="container-fluid g-0"><div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div></div>

    <div class="main_content_iner">
        <div class="container-fluid p-0 sm_padding_15px">

            <div class="list-page-head">
                <div class="page-heading">
                    <h4 class="mb-0 fw-bold">Platform Settings</h4>
                    <small class="text-muted">Commercial parameters shared across the platform — currently the school-membership commission model.</small>
                </div>
                <a href="school-memberships.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-id-card me-1"></i> Memberships</a>
            </div>

            <?php if (!empty($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php unset($_SESSION['success_message']); endif; ?>
            <?php if ($page_message): ?>
                <div class="alert alert-<?= htmlspecialchars($page_message_type) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($page_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-4"><div class="stat-box bg-stat-blue"><i class="fas fa-id-card big-icon"></i><div class="num"><?= $s_active_members ?></div><div class="lbl">Active Memberships</div></div></div>
                <div class="col-6 col-lg-4"><div class="stat-box bg-stat-warn"><i class="fas fa-hourglass-half big-icon"></i><div class="num"><?= $s_commission_held ?></div><div class="lbl">Commission Held (refund window)</div></div></div>
                <div class="col-6 col-lg-4"><div class="stat-box bg-stat-green"><i class="fas fa-rupee-sign big-icon"></i><div class="num">&#8377;<?= number_format((float) $sum) ?></div><div class="lbl">Held Commission Value</div></div></div>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="white_card">
                        <div class="white_card_header"><div class="box_header"><div class="main-title"><h3 class="m-0">School Membership Commercials</h3></div></div></div>
                        <div class="white_card_body">
                            <form method="post" class="row g-3">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="save_platform_settings" value="1">

                                <div class="col-md-6">
                                    <label class="form-label">School commission (%)</label>
                                    <input type="number" name="school_commission_percent" class="form-control" min="0" max="100" step="0.5"
                                           value="<?= $get('school_commission_percent', '10') ?>">
                                    <small class="text-muted">Share of each paid membership the linked school earns. Snapshotted onto the membership at payment time — changing this only affects future purchases.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Refund / cancellation window (days)</label>
                                    <input type="number" name="membership_refund_window_days" class="form-control" min="0" max="90" step="1"
                                           value="<?= $get('membership_refund_window_days', '7') ?>">
                                    <small class="text-muted">How long after payment a membership can still be cancelled &amp; refunded. Commission stays held until this window closes.</small>
                                </div>

                                <div class="col-12">
                                    <button class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Settings</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="white_card">
                        <div class="white_card_header"><div class="box_header"><div class="main-title"><h3 class="m-0">How this is used</h3></div></div></div>
                        <div class="white_card_body">
                            <p class="small text-muted mb-2">On every membership purchase (<code>school/student/membership.php</code>, <code>school/parent-consent.php</code>):</p>
                            <ul class="small text-muted">
                                <li>Commission = amount paid × commission %, snapshotted on the row.</li>
                                <li>Commission starts <strong>held</strong> and becomes <strong>payable</strong> once the refund window closes without a cancellation.</li>
                                <li>Admin marks a payable commission <strong>paid out</strong> from <a href="school-memberships.php">School Memberships</a>.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php include "footer.php"; ?>
</section>
</body>
</html>

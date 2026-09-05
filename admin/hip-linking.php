<?php
/**
 * Admin → ABHA Health ID → HIP Care-Context Linking
 *
 * Operational visibility for the ABDM HIP-initiated care-context linking
 * feature (lib/HipApi.php + telemedicine/api/abdm-webhook.php +
 * scripts/abdm-hip-worker.php). Read-only config + three table viewers +
 * a manual retry for failed links.
 *
 * Config is displayed read-only — .env stays the single source of truth
 * (ABDM_HIP_ID / ABDM_WEBHOOK_SECRET etc. via config/abdm.php).
 */
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/HipLinking.php';
require_once dirname(__DIR__) . '/config/abdm.php';
admin_jwt_guard();

$csrf = Security::csrfToken();

$tbl = fn($t) => (bool) $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'")->num_rows;
$tables_ok         = $tbl('abdm_care_context_links') && $tbl('abdm_webhook_log') && $tbl('abdm_link_tokens');
$consent_tables_ok = $tbl('abha_consents') && $tbl('abha_hi_requests');

/* ── Retry a failed care-context link (PRG) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'retry_link') {
    if (!Security::verifyCsrf($_POST['_csrf'] ?? '')) {
        $_SESSION['hip_error'] = 'Security token expired — please retry.';
    } elseif (!$tables_ok) {
        $_SESSION['hip_error'] = 'HIP tables are not present (run database/migration_abdm_hip_linking.sql).';
    } else {
        $id     = (int) ($_POST['id'] ?? 0);
        $newReq = HipLinking::newRequestId();
        // Fresh request id (the webhook matches on it), reset the worker's
        // 48h give-up clock, back to 'pending' for the next worker cycle.
        $st = $conn->prepare(
            "UPDATE abdm_care_context_links
             SET status = 'pending', webhook_received_at = NULL,
                 created_at = NOW(), request_id = ?
             WHERE id = ? AND status = 'failed'"
        );
        $st->bind_param('si', $newReq, $id);
        $st->execute();
        $_SESSION[$st->affected_rows > 0 ? 'hip_success' : 'hip_error'] =
            $st->affected_rows > 0
                ? "Link #$id re-queued — the worker will retry on its next run."
                : "Link #$id is not in a failed state.";
        $st->close();
    }
    header('Location: hip-linking.php?' . http_build_query([
        'view'   => $_POST['view']   ?? 'links',
        'status' => $_POST['status'] ?? '',
    ]));
    exit;
}

$success = $_SESSION['hip_success'] ?? '';
$error   = $_SESSION['hip_error']   ?? '';
unset($_SESSION['hip_success'], $_SESSION['hip_error']);

/* ── filters / paging ── */
$view    = in_array($_GET['view'] ?? '', ['links', 'webhooks', 'tokens', 'consents', 'hi_requests'], true) ? $_GET['view'] : 'links';
$fstatus = trim((string) ($_GET['status'] ?? ''));
$page    = max(1, (int) ($_GET['p'] ?? 1));
$per     = 25;
$offset  = ($page - 1) * $per;

/* ── config (read-only) ── */
$hip_id        = defined('ABDM_HIP_ID') ? ABDM_HIP_ID : '';
$hip_name      = defined('ABDM_HIP_NAME') ? ABDM_HIP_NAME : '';
$hiecm_base    = defined('ABDM_HIECM_BASE_URL') ? ABDM_HIECM_BASE_URL : '';
$hip_ready     = defined('ABDM_HIP_CONFIGURED') && ABDM_HIP_CONFIGURED;
$wh_secret_set = defined('ABDM_WEBHOOK_SECRET') && ABDM_WEBHOOK_SECRET !== '';
$wh_allow      = defined('ABDM_WEBHOOK_ALLOWED_IPS') ? trim((string) ABDM_WEBHOOK_ALLOWED_IPS) : '';
$bridge_base   = rtrim(BASE_URL, '/');

/* ── stats ── */
$stat = fn($sql) => $tables_ok ? (int) ($conn->query($sql)->fetch_assoc()['c'] ?? 0) : 0;
$s_pending = $stat("SELECT COUNT(*) c FROM abdm_care_context_links WHERE status='pending'");
$s_linked  = $stat("SELECT COUNT(*) c FROM abdm_care_context_links WHERE status='linked'");
$s_failed  = $stat("SELECT COUNT(*) c FROM abdm_care_context_links WHERE status='failed'");
$s_tok     = $stat("SELECT COUNT(*) c FROM abdm_link_tokens WHERE status='received' AND expires_at > NOW()");
$s_unproc  = $stat("SELECT COUNT(*) c FROM abdm_webhook_log WHERE processed=0");
$cstat = fn($sql) => $consent_tables_ok ? (int) ($conn->query($sql)->fetch_assoc()['c'] ?? 0) : 0;
$s_consent_granted = $cstat("SELECT COUNT(*) c FROM abha_consents WHERE status='granted'");
$s_consent_revoked = $cstat("SELECT COUNT(*) c FROM abha_consents WHERE status='revoked'");
$s_hi_ack          = $cstat("SELECT COUNT(*) c FROM abha_hi_requests WHERE status='acknowledged'");
$s_hi_ready        = $cstat("SELECT COUNT(*) c FROM abha_hi_requests WHERE status='ready_for_push'");
$last_wh   = $tables_ok
    ? ($conn->query("SELECT received_at FROM abdm_webhook_log ORDER BY id DESC LIMIT 1")->fetch_assoc()['received_at'] ?? null)
    : null;

/* ── viewer query ── */
$rows = [];
$total = 0;
$filters = [];

if ($tables_ok) {
    if ($view === 'links') {
        $filters = ['' => 'All', 'pending' => 'Pending', 'linked' => 'Linked', 'failed' => 'Failed'];
        $where = ''; $params = []; $types = '';
        if (isset($filters[$fstatus]) && $fstatus !== '') { $where = 'WHERE ccl.status = ?'; $params[] = $fstatus; $types .= 's'; }
        if ($params) {
            $cs = $conn->prepare("SELECT COUNT(*) c FROM abdm_care_context_links ccl WHERE ccl.status = ?");
            $cs->bind_param('s', $fstatus); $cs->execute();
            $total = (int) ($cs->get_result()->fetch_assoc()['c'] ?? 0); $cs->close();
        } else {
            $total = (int) ($conn->query("SELECT COUNT(*) c FROM abdm_care_context_links")->fetch_assoc()['c'] ?? 0);
        }
        $sql = "SELECT ccl.id, ccl.prescription_id, ccl.care_context_reference, ccl.hi_type,
                       ccl.status, ccl.request_id, ccl.webhook_received_at, ccl.created_at,
                       p.patient_id,
                       TRIM(CONCAT(COALESCE(u.name,''),' ',COALESCE(u.last_name,''))) AS patient_name
                FROM abdm_care_context_links ccl
                LEFT JOIN prescriptions p ON p.id = ccl.prescription_id
                LEFT JOIN users u ON u.id = p.patient_id
                $where ORDER BY ccl.id DESC LIMIT ? OFFSET ?";
        $params[] = $per; $params[] = $offset; $types .= 'ii';
        $st = $conn->prepare($sql); $st->bind_param($types, ...$params); $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

    } elseif ($view === 'webhooks') {
        $filters = ['' => 'All', '0' => 'Unprocessed', '1' => 'Processed'];
        $where = ''; $params = []; $types = '';
        if ($fstatus === '0' || $fstatus === '1') { $where = 'WHERE processed = ?'; $params[] = (int) $fstatus; $types .= 'i'; }
        if ($params) {
            $cs = $conn->prepare("SELECT COUNT(*) c FROM abdm_webhook_log WHERE processed = ?");
            $p0 = (int) $fstatus; $cs->bind_param('i', $p0); $cs->execute();
            $total = (int) ($cs->get_result()->fetch_assoc()['c'] ?? 0); $cs->close();
        } else {
            $total = (int) ($conn->query("SELECT COUNT(*) c FROM abdm_webhook_log")->fetch_assoc()['c'] ?? 0);
        }
        $sql = "SELECT id, request_id, callback_type, processed, received_at,
                       CHAR_LENGTH(raw_payload) AS plen, LEFT(raw_payload, 300) AS peek
                FROM abdm_webhook_log $where ORDER BY id DESC LIMIT ? OFFSET ?";
        $params[] = $per; $params[] = $offset; $types .= 'ii';
        $st = $conn->prepare($sql); $st->bind_param($types, ...$params); $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

    } elseif ($view === 'tokens') {
        $filters = ['' => 'All', 'pending' => 'Pending', 'received' => 'Received', 'expired' => 'Expired'];
        $where = ''; $params = []; $types = '';
        if (isset($filters[$fstatus]) && $fstatus !== '') { $where = 'WHERE lt.status = ?'; $params[] = $fstatus; $types .= 's'; }
        if ($params) {
            $cs = $conn->prepare("SELECT COUNT(*) c FROM abdm_link_tokens lt WHERE lt.status = ?");
            $cs->bind_param('s', $fstatus); $cs->execute();
            $total = (int) ($cs->get_result()->fetch_assoc()['c'] ?? 0); $cs->close();
        } else {
            $total = (int) ($conn->query("SELECT COUNT(*) c FROM abdm_link_tokens")->fetch_assoc()['c'] ?? 0);
        }
        $sql = "SELECT lt.id, lt.patient_id, lt.abha_address, lt.status, lt.request_id,
                       lt.expires_at, lt.created_at, (lt.link_token IS NOT NULL) AS has_token,
                       TRIM(CONCAT(COALESCE(u.name,''),' ',COALESCE(u.last_name,''))) AS patient_name
                FROM abdm_link_tokens lt
                LEFT JOIN users u ON u.id = lt.patient_id
                $where ORDER BY lt.id DESC LIMIT ? OFFSET ?";
        $params[] = $per; $params[] = $offset; $types .= 'ii';
        $st = $conn->prepare($sql); $st->bind_param($types, ...$params); $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

    } elseif ($view === 'consents' && $consent_tables_ok) {
        $filters = ['' => 'All', 'granted' => 'Granted', 'revoked' => 'Revoked'];
        $where = ''; $params = []; $types = '';
        if (isset($filters[$fstatus]) && $fstatus !== '') { $where = 'WHERE c.status = ?'; $params[] = $fstatus; $types .= 's'; }
        if ($params) {
            $cs = $conn->prepare("SELECT COUNT(*) c FROM abha_consents c WHERE c.status = ?");
            $cs->bind_param('s', $fstatus); $cs->execute();
            $total = (int) ($cs->get_result()->fetch_assoc()['c'] ?? 0); $cs->close();
        } else {
            $total = (int) ($conn->query("SELECT COUNT(*) c FROM abha_consents")->fetch_assoc()['c'] ?? 0);
        }
        $sql = "SELECT c.id, c.consent_id, c.status, c.patient_id, c.abha_address, c.hiu_id,
                       c.purpose_code, c.hi_types, c.date_range_from, c.date_range_to, c.data_erase_at,
                       c.created_at, c.updated_at,
                       TRIM(CONCAT(COALESCE(u.name,''),' ',COALESCE(u.last_name,''))) AS patient_name
                FROM abha_consents c
                LEFT JOIN users u ON u.id = c.patient_id
                $where ORDER BY c.id DESC LIMIT ? OFFSET ?";
        $params[] = $per; $params[] = $offset; $types .= 'ii';
        $st = $conn->prepare($sql); $st->bind_param($types, ...$params); $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

    } elseif ($view === 'hi_requests' && $consent_tables_ok) {
        $filters = ['' => 'All', 'pending' => 'Pending', 'acknowledged' => 'Acknowledged', 'ready_for_push' => 'Ready for push', 'failed' => 'Failed'];
        $where = ''; $params = []; $types = '';
        if (isset($filters[$fstatus]) && $fstatus !== '') { $where = 'WHERE r.status = ?'; $params[] = $fstatus; $types .= 's'; }
        if ($params) {
            $cs = $conn->prepare("SELECT COUNT(*) c FROM abha_hi_requests r WHERE r.status = ?");
            $cs->bind_param('s', $fstatus); $cs->execute();
            $total = (int) ($cs->get_result()->fetch_assoc()['c'] ?? 0); $cs->close();
        } else {
            $total = (int) ($conn->query("SELECT COUNT(*) c FROM abha_hi_requests")->fetch_assoc()['c'] ?? 0);
        }
        $sql = "SELECT r.id, r.transaction_id, r.consent_id, r.status, r.date_range_from, r.date_range_to,
                       r.data_push_url, (r.key_material IS NOT NULL AND r.key_material <> '{}') AS has_km,
                       r.error_detail, r.created_at, r.updated_at,
                       c.status AS consent_status
                FROM abha_hi_requests r
                LEFT JOIN abha_consents c ON c.consent_id = r.consent_id
                $where ORDER BY r.id DESC LIMIT ? OFFSET ?";
        $params[] = $per; $params[] = $offset; $types .= 'ii';
        $st = $conn->prepare($sql); $st->bind_param($types, ...$params); $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
    }
}

$pages = max(1, (int) ceil($total / $per));
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
$qs = fn(array $x) => 'hip-linking.php?' . http_build_query(array_merge(['view' => $view, 'status' => $fstatus], $x));

function status_badge(string $s): string
{
    $map = [
        'pending'        => 'badge bg-warning text-dark',
        'linked'         => 'badge bg-success',
        'received'       => 'badge bg-success',
        'granted'        => 'badge bg-success',
        'acknowledged'   => 'badge bg-info text-dark',
        'ready_for_push' => 'badge bg-primary',
        'failed'         => 'badge bg-danger',
        'revoked'        => 'badge bg-danger',
        'expired'        => 'badge bg-secondary',
    ];
    return '<span class="' . ($map[$s] ?? 'badge bg-light text-dark') . '">' . htmlspecialchars($s) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>HIP Care-Context Linking | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .cfg-row { display:flex; gap:10px; padding:9px 0; border-bottom:1px solid #eef0f5; font-size:.86rem; }
        .cfg-row:last-child { border-bottom:0; }
        .cfg-row .k { width:190px; color:#6b7089; flex-shrink:0; }
        .cfg-row .v { font-weight:600; word-break:break-all; }
        code.inline { background:#f1f3f9; padding:1px 6px; border-radius:5px; font-size:.82em; word-break:break-all; }
        .hip-tabs { display:flex; gap:6px; margin-bottom:14px; flex-wrap:wrap; }
        .hip-tabs a { padding:7px 14px; border-radius:8px; font-size:.85rem; font-weight:600; color:#6b7089; background:#f1f3f9; text-decoration:none; }
        .hip-tabs a.on { background:#0C74C5; color:#fff; }
        .hip-table { font-size:.82rem; }
        .hip-table td { vertical-align:middle; }
        .hip-table .mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.78rem; }
        .peek { max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#6b7089; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0"><div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div></div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">

                <div class="list-page-head">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">HIP Care-Context Linking</h4>
                        <small class="text-muted">ABDM HIP-initiated linking — config, callbacks and stuck-link recovery</small>
                    </div>
                    <a href="abha-management.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-id-card me-1"></i> ABHA Dashboard</a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= $h($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= $h($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (!$tables_ok): ?>
                    <div class="alert alert-warning"><i class="fas fa-database me-2"></i>The HIP tables are not present. Run <code class="inline">database/migration_abdm_hip_linking.sql</code>.</div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-warn"><i class="fas fa-hourglass-half big-icon"></i><div class="num"><?= $s_pending ?></div><div class="lbl">Pending Links</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-green"><i class="fas fa-link big-icon"></i><div class="num"><?= $s_linked ?></div><div class="lbl">Linked</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-blue"><i class="fas fa-times-circle big-icon"></i><div class="num"><?= $s_failed ?></div><div class="lbl">Failed</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-teal"><i class="fas fa-key big-icon"></i><div class="num"><?= $s_tok ?></div><div class="lbl">Active Link Tokens</div></div></div>
                </div>

                <?php if ($consent_tables_ok): ?>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-green"><i class="fas fa-file-signature big-icon"></i><div class="num"><?= $s_consent_granted ?></div><div class="lbl">Consents Granted</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-blue"><i class="fas fa-ban big-icon"></i><div class="num"><?= $s_consent_revoked ?></div><div class="lbl">Consents Revoked</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-teal"><i class="fas fa-inbox big-icon"></i><div class="num"><?= $s_hi_ack ?></div><div class="lbl">HI Requests Ack'd</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-warn"><i class="fas fa-paper-plane big-icon"></i><div class="num"><?= $s_hi_ready ?></div><div class="lbl">Ready for Push</div></div></div>
                </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Config -->
                    <div class="col-lg-5">
                        <div class="white_card">
                            <div class="white_card_header"><div class="box_header"><div class="main-title"><h3 class="m-0">Configuration <span class="text-muted fw-normal" style="font-size:.7rem;">(read-only — edit in .env)</span></h3></div></div></div>
                            <div class="white_card_body">
                                <div class="cfg-row"><div class="k">Status</div><div class="v"><?= $hip_ready ? '<span class="badge bg-success">Configured</span>' : '<span class="badge bg-secondary">Not configured</span>' ?></div></div>
                                <div class="cfg-row"><div class="k">ABDM_HIP_ID</div><div class="v"><?= $hip_id !== '' ? $h($hip_id) : '<span class="text-muted">— not set —</span>' ?></div></div>
                                <div class="cfg-row"><div class="k">ABDM_HIP_NAME</div><div class="v"><?= $h($hip_name ?: '—') ?></div></div>
                                <div class="cfg-row"><div class="k">HIECM base URL</div><div class="v"><code class="inline"><?= $h($hiecm_base ?: '—') ?></code></div></div>
                                <div class="cfg-row"><div class="k">Webhook secret (?k=)</div><div class="v"><?= $wh_secret_set ? '<span class="badge bg-success">set</span>' : '<span class="badge bg-warning text-dark">not set</span>' ?></div></div>
                                <div class="cfg-row"><div class="k">Webhook IP allowlist</div><div class="v"><?= $wh_allow !== '' ? $h($wh_allow) : '<span class="text-muted">any IP</span>' ?></div></div>
                                <div class="cfg-row"><div class="k">Last webhook received</div><div class="v"><?= $last_wh ? $h($last_wh) : '<span class="text-muted">never</span>' ?><?= $s_unproc > 0 ? ' <span class="badge bg-warning text-dark ms-1">' . $s_unproc . ' unprocessed</span>' : '' ?></div></div>
                            </div>
                        </div>

                        <div class="white_card mt-4">
                            <div class="white_card_header"><div class="box_header"><div class="main-title"><h3 class="m-0">Bridge callback URL</h3></div></div></div>
                            <div class="white_card_body" style="font-size:.83rem;">
                                <p class="text-muted mb-2">Register this base URL with ABDM. It appends the three fixed sub-paths (routed by <code class="inline">.htaccess</code>).</p>
                                <div class="cfg-row"><div class="k">Bridge base</div><div class="v"><code class="inline"><?= $h($bridge_base) ?>/<?= $wh_secret_set ? '?k=&lt;secret&gt;' : '' ?></code></div></div>
                                <div class="cfg-row"><div class="k">token callback</div><div class="v"><code class="inline">/api/v3/hip/token/on-generate-token</code></div></div>
                                <div class="cfg-row"><div class="k">carecontext</div><div class="v"><code class="inline">/api/v3/link/on_carecontext</code></div></div>
                                <div class="cfg-row"><div class="k">notify</div><div class="v"><code class="inline">/api/v3/links/context/on-notify</code></div></div>
                            </div>
                        </div>
                    </div>

                    <!-- Viewers -->
                    <div class="col-lg-7">
                        <div class="white_card">
                            <div class="white_card_body">
                                <div class="hip-tabs">
                                    <a href="<?= $h($qs(['view' => 'links', 'status' => '', 'p' => 1])) ?>" class="<?= $view === 'links' ? 'on' : '' ?>">Care-Context Links</a>
                                    <a href="<?= $h($qs(['view' => 'webhooks', 'status' => '', 'p' => 1])) ?>" class="<?= $view === 'webhooks' ? 'on' : '' ?>">Webhook Log</a>
                                    <a href="<?= $h($qs(['view' => 'tokens', 'status' => '', 'p' => 1])) ?>" class="<?= $view === 'tokens' ? 'on' : '' ?>">Link Tokens</a>
                                    <?php if ($consent_tables_ok): ?>
                                    <a href="<?= $h($qs(['view' => 'consents', 'status' => '', 'p' => 1])) ?>" class="<?= $view === 'consents' ? 'on' : '' ?>">HI Consents</a>
                                    <a href="<?= $h($qs(['view' => 'hi_requests', 'status' => '', 'p' => 1])) ?>" class="<?= $view === 'hi_requests' ? 'on' : '' ?>">HI Data Requests</a>
                                    <?php endif; ?>
                                </div>

                                <form method="get" class="d-flex align-items-center gap-2 mb-3">
                                    <input type="hidden" name="view" value="<?= $h($view) ?>">
                                    <label class="text-muted" style="font-size:.82rem;">Filter</label>
                                    <select name="status" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                                        <?php foreach ($filters as $val => $lbl): ?>
                                            <option value="<?= $h($val) ?>" <?= (string) $fstatus === (string) $val ? 'selected' : '' ?>><?= $h($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="text-muted ms-auto" style="font-size:.8rem;"><?= number_format($total) ?> row<?= $total === 1 ? '' : 's' ?></span>
                                </form>

                                <div class="table-responsive">
                                <?php if ($view === 'links'): ?>
                                    <table class="table table-sm hip-table">
                                        <thead><tr><th>#</th><th>Patient</th><th>Care Context</th><th>Type</th><th>Status</th><th>Created</th><th>Webhook</th><th></th></tr></thead>
                                        <tbody>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td><?= (int) $r['id'] ?></td>
                                                <td><?= $r['patient_name'] !== '' ? $h($r['patient_name']) : '<span class="text-muted">#' . (int) $r['patient_id'] . '</span>' ?></td>
                                                <td class="mono"><?= $h($r['care_context_reference']) ?></td>
                                                <td><?= $h($r['hi_type']) ?></td>
                                                <td><?= status_badge($r['status']) ?></td>
                                                <td class="mono"><?= $h($r['created_at']) ?></td>
                                                <td class="mono"><?= $r['webhook_received_at'] ? $h($r['webhook_received_at']) : '—' ?></td>
                                                <td>
                                                    <?php if ($r['status'] === 'failed'): ?>
                                                        <form method="post" onsubmit="return confirm('Re-queue link #<?= (int) $r['id'] ?>?');" style="display:inline;">
                                                            <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                                                            <input type="hidden" name="action" value="retry_link">
                                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                            <input type="hidden" name="view" value="<?= $h($view) ?>">
                                                            <input type="hidden" name="status" value="<?= $h($fstatus) ?>">
                                                            <button class="btn btn-outline-primary btn-sm py-0"><i class="fas fa-redo"></i> Retry</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-3">No rows.</td></tr><?php endif; ?>
                                        </tbody>
                                    </table>

                                <?php elseif ($view === 'webhooks'): ?>
                                    <table class="table table-sm hip-table">
                                        <thead><tr><th>#</th><th>Type</th><th>Request ID</th><th>Done</th><th>Received</th><th>Payload</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td><?= (int) $r['id'] ?></td>
                                                <td><?= $h($r['callback_type'] ?: '—') ?></td>
                                                <td class="mono"><?= $r['request_id'] ? $h($r['request_id']) : '<span class="text-muted">—</span>' ?></td>
                                                <td><?= $r['processed'] ? '<span class="badge bg-success">yes</span>' : '<span class="badge bg-warning text-dark">no</span>' ?></td>
                                                <td class="mono"><?= $h($r['received_at']) ?></td>
                                                <td class="peek mono" title="<?= $h($r['peek']) ?>"><?= $h($r['peek']) ?><?= $r['plen'] > 300 ? '…' : '' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-3">No rows.</td></tr><?php endif; ?>
                                        </tbody>
                                    </table>

                                <?php elseif ($view === 'tokens'): ?>
                                    <table class="table table-sm hip-table">
                                        <thead><tr><th>#</th><th>Patient</th><th>ABHA Address</th><th>Status</th><th>Token</th><th>Expires</th><th>Created</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td><?= (int) $r['id'] ?></td>
                                                <td><?= $r['patient_name'] !== '' ? $h($r['patient_name']) : '<span class="text-muted">#' . (int) $r['patient_id'] . '</span>' ?></td>
                                                <td class="mono"><?= $h($r['abha_address']) ?></td>
                                                <td><?= status_badge($r['status']) ?></td>
                                                <td><?= $r['has_token'] ? '<span class="badge bg-success">held</span>' : '<span class="text-muted">—</span>' ?></td>
                                                <td class="mono"><?= $h($r['expires_at']) ?></td>
                                                <td class="mono"><?= $h($r['created_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-3">No rows.</td></tr><?php endif; ?>
                                        </tbody>
                                    </table>

                                <?php elseif ($view === 'consents'): ?>
                                    <table class="table table-sm hip-table">
                                        <thead><tr><th>#</th><th>Consent ID</th><th>Patient</th><th>HIU</th><th>Purpose</th><th>HI Types</th><th>Date Range</th><th>Erase At</th><th>Status</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($rows as $r): $hit = implode(', ', array_map('strval', (array) (json_decode((string) $r['hi_types'], true) ?: []))); ?>
                                            <tr>
                                                <td><?= (int) $r['id'] ?></td>
                                                <td class="mono" title="<?= $h($r['consent_id']) ?>"><?= $h(substr((string) $r['consent_id'], 0, 12)) ?>…</td>
                                                <td><?= $r['patient_name'] !== '' ? $h($r['patient_name']) : ($r['abha_address'] ? '<span class="mono">' . $h($r['abha_address']) . '</span>' : '<span class="text-muted">not held</span>') ?></td>
                                                <td class="mono"><?= $h($r['hiu_id'] ?: '—') ?></td>
                                                <td><?= $h($r['purpose_code'] ?: '—') ?></td>
                                                <td style="font-size:.76rem;"><?= $h($hit ?: '—') ?></td>
                                                <td class="mono" style="font-size:.74rem;"><?= $h(substr((string) $r['date_range_from'], 0, 10)) ?> → <?= $h(substr((string) $r['date_range_to'], 0, 10)) ?></td>
                                                <td class="mono" style="font-size:.74rem;"><?= $h(substr((string) $r['data_erase_at'], 0, 10) ?: '—') ?></td>
                                                <td><?= status_badge($r['status']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-3">No rows.</td></tr><?php endif; ?>
                                        </tbody>
                                    </table>

                                <?php elseif ($view === 'hi_requests'): ?>
                                    <table class="table table-sm hip-table">
                                        <thead><tr><th>#</th><th>Txn ID</th><th>Consent</th><th>Status</th><th>Date Range</th><th>Push URL</th><th>Key Mat.</th><th>Error</th><th>Created</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td><?= (int) $r['id'] ?></td>
                                                <td class="mono"><?= $h($r['transaction_id'] ?: '—') ?></td>
                                                <td class="mono" title="<?= $h($r['consent_id']) ?>"><?= $h(substr((string) $r['consent_id'], 0, 12)) ?>…<?= $r['consent_status'] ? ' ' . status_badge($r['consent_status']) : ' <span class="badge bg-light text-dark">?</span>' ?></td>
                                                <td><?= status_badge($r['status']) ?></td>
                                                <td class="mono" style="font-size:.74rem;"><?= $h(substr((string) $r['date_range_from'], 0, 10)) ?> → <?= $h(substr((string) $r['date_range_to'], 0, 10)) ?></td>
                                                <td class="peek mono" title="<?= $h($r['data_push_url']) ?>"><?= $h($r['data_push_url'] ?: '—') ?></td>
                                                <td><?= $r['has_km'] ? '<span class="badge bg-success">held</span>' : '<span class="text-muted">—</span>' ?></td>
                                                <td style="font-size:.74rem;color:#b91c1c;"><?= $h($r['error_detail'] ?: '') ?></td>
                                                <td class="mono"><?= $h($r['created_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-3">No rows.</td></tr><?php endif; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                                </div>

                                <?php if ($pages > 1): ?>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <a class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $h($qs(['p' => $page - 1])) ?>">&larr; Prev</a>
                                        <span class="text-muted" style="font-size:.82rem;">Page <?= $page ?> / <?= $pages ?></span>
                                        <a class="btn btn-sm btn-outline-secondary <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $h($qs(['p' => $page + 1])) ?>">Next &rarr;</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include "footer.php"; ?>
    </section>
</div>
</body>
</html>

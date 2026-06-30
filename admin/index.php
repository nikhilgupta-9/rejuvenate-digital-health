<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include "db-conn.php";
include_once "auth/login-sessions.php";

// Core stats
$total_revenue   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(order_total),0) as t FROM orders_new WHERE status='completed'"))['t'];
$total_orders    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders_new"))['c'];
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
$total_doctors   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM doctors WHERE status='Active'"))['c'];
$total_schools   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM schools WHERE status='Active'"))['c'];
$total_members   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members"))['c'];
$total_appts     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM appointments"))['c'];
$pending_schools = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM schools WHERE status='Pending'"))['c'] ?? 0);

// ABHA stats — users (patients)
$abha_stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
      SUM(abha_linked=1)  as users_linked,
      SUM(abha_verified=1) as users_verified,
      COUNT(*) as users_total
    FROM users WHERE status='Active'
"));
// ABHA stats — school members
$abha_school = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
      SUM(abha_linked=1)   as members_linked,
      SUM(abha_verified=1) as members_verified,
      COUNT(*) as members_total
    FROM school_members WHERE status='Active'
"));
$abha_total_linked   = (int)($abha_stats['users_linked']   ?? 0) + (int)($abha_school['members_linked']   ?? 0);
$abha_total_verified = (int)($abha_stats['users_verified'] ?? 0) + (int)($abha_school['members_verified'] ?? 0);
$abha_total_people   = (int)($abha_stats['users_total']    ?? 0) + (int)($abha_school['members_total']    ?? 0);
$abha_pending_req    = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM user_abha_requests WHERE status='Pending'"))['c'] ?? 0)
                     + (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM abha_link_requests WHERE status='Pending'"))['c'] ?? 0);
// Pending appointments
$pending_appts = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM appointments WHERE status='Pending'"))['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Admin Panel | Dashboard</title>
    <?php include "links.php"; ?>
</head>

<body>
    <div class="wrapper">
        <?php include "header.php"; ?>

        <section class="main_content dashboard_part">
            <div class="container-fluid g-0">
                <div class="row">
                    <div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div>
                </div>
            </div>

            <div class="main_content_iner">
                <div class="container-fluid p-0">

                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="mb-0 fw-bold">Dashboard Overview</h4>
                            <small class="text-muted">Welcome back, <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?> &nbsp;|&nbsp; <?= date('l, d F Y') ?></small>
                        </div>
                        <?php if ($pending_schools > 0): ?>
                            <a href="schools-list.php?status=Pending" class="btn btn-sm btn-danger">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <?= (int)$pending_schools ?> School<?= (int)$pending_schools > 1 ? 's' : '' ?> Pending Approval
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- ── Core Metrics ── -->
                    <p class="section-heading">Core Metrics</p>
                    <div class="row g-3">
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <div class="dash-stat-card card-blue">
                                <i class="fas fa-rupee-sign icon-bg"></i>
                                <div class="stat-num">₹<?= number_format($total_revenue) ?></div>
                                <div class="stat-lbl">Total Revenue</div>
                                <div class="stat-sub"><a href="orders.php" class="text-white opacity-75">View orders →</a></div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <div class="dash-stat-card card-green">
                                <i class="fas fa-shopping-cart icon-bg"></i>
                                <div class="stat-num"><?= $total_orders ?></div>
                                <div class="stat-lbl">Total Orders</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <div class="dash-stat-card card-cyan">
                                <i class="fas fa-users icon-bg"></i>
                                <div class="stat-num"><?= $total_customers ?></div>
                                <div class="stat-lbl">Patients Registered</div>
                                <div class="stat-sub"><a href="all-customers.php" class="text-white opacity-75">View patients →</a></div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <div class="dash-stat-card card-orange">
                                <i class="fas fa-user-md icon-bg"></i>
                                <div class="stat-num"><?= $total_doctors ?></div>
                                <div class="stat-lbl">Active Doctors</div>
                                <div class="stat-sub"><a href="doctors-list.php" class="text-white opacity-75">View doctors →</a></div>
                            </div>
                        </div>
                    </div>

                    <!-- ── School Module ── -->
                    <p class="section-heading">
                        School Health Module
                        <?php if ($pending_schools > 0): ?>
                            <span class="pending-badge ms-2"><?= (int)$pending_schools ?> pending</span>
                        <?php endif; ?>
                    </p>
                    <div class="row g-3">
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <div class="dash-stat-card card-purple">
                                <i class="fas fa-school icon-bg"></i>
                                <div class="stat-num"><?= $total_schools ?></div>
                                <div class="stat-lbl">Active Schools</div>
                                <div class="stat-sub"><a href="schools-list.php" class="text-white opacity-75">Manage schools →</a></div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <div class="dash-stat-card card-teal">
                                <i class="fas fa-user-graduate icon-bg"></i>
                                <div class="stat-num"><?= $total_members ?></div>
                                <div class="stat-lbl">Total Members</div>
                                <div class="stat-sub">Students · Teachers · Staff</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <div class="dash-stat-card card-red">
                                <i class="fas fa-clock icon-bg"></i>
                                <div class="stat-num"><?= (int)$pending_schools ?></div>
                                <div class="stat-lbl">Pending Approvals</div>
                                <div class="stat-sub"><a href="schools-list.php?status=Pending" class="text-white opacity-75">Review now →</a></div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <div class="dash-stat-card card-indigo">
                                <i class="fas fa-calendar-check icon-bg"></i>
                                <div class="stat-num"><?= $total_appts ?></div>
                                <div class="stat-lbl">Appointments</div>
                                <div class="stat-sub"><a href="all-appointment.php" class="text-white opacity-75">View all →</a></div>
                            </div>
                        </div>
                    </div>

                    <!-- ── ABHA Digital Health ── -->
                    <p class="section-heading" style="border-left:4px solid #00875a;">
                        <span style="color:#00875a;"><i class="fas fa-heartbeat me-2"></i>ABHA — Ayushman Bharat Health Account</span>
                        <?php if ($abha_pending_req > 0): ?>
                            <a href="abha-management.php?tab=requests" class="badge bg-danger ms-2" style="font-size:.7rem;text-decoration:none;"><?= $abha_pending_req ?> pending requests</a>
                        <?php endif; ?>
                    </p>
                    <?php
                      $abha_link_pct = $abha_total_people > 0 ? round($abha_total_linked/$abha_total_people*100) : 0;
                      $user_link_pct = $abha_stats['users_total'] > 0 ? round($abha_stats['users_linked']/$abha_stats['users_total']*100) : 0;
                      $memb_link_pct = $abha_school['members_total'] > 0 ? round($abha_school['members_linked']/$abha_school['members_total']*100) : 0;
                    ?>
                    <div class="row g-3 mb-3">
                        <!-- Big ABHA card -->
                        <div class="col-xl-4 col-md-6">
                            <div class="card border-0 shadow-sm h-100" style="border-top:4px solid #00875a !important;border-radius:12px;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <div style="width:44px;height:44px;background:#d1fae5;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#00875a;flex-shrink:0;"><i class="fas fa-id-card"></i></div>
                                        <div>
                                            <div style="font-size:1.4rem;font-weight:700;color:#111;"><?= $abha_total_linked ?><span style="font-size:.8rem;color:#6b7280;font-weight:400;"> / <?= $abha_total_people ?></span></div>
                                            <div style="font-size:.72rem;color:#6b7280;">Total ABHA Linked (All Portals)</div>
                                        </div>
                                    </div>
                                    <div style="height:6px;border-radius:3px;background:#e5e7eb;overflow:hidden;margin-bottom:8px;">
                                        <div style="height:100%;border-radius:3px;background:#00875a;width:<?= $abha_link_pct ?>%;"></div>
                                    </div>
                                    <div class="d-flex justify-content-between" style="font-size:.72rem;color:#6b7280;">
                                        <span><i class="fas fa-shield-alt me-1 text-primary"></i><?= $abha_total_verified ?> Verified</span>
                                        <span><?= $abha_link_pct ?>% Coverage</span>
                                    </div>
                                    <a href="abha-management.php" class="btn btn-sm w-100 mt-2" style="background:#00875a;color:#fff;font-size:.75rem;"><i class="fas fa-cog me-1"></i>Manage All ABHA</a>
                                </div>
                            </div>
                        </div>
                        <!-- Patient ABHA -->
                        <div class="col-xl-4 col-md-3 col-sm-6">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                                <div class="card-body p-3">
                                    <div style="font-size:.72rem;font-weight:700;color:#6b7280;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">Patients (Portal Users)</div>
                                    <div style="font-size:1.5rem;font-weight:700;color:#0C74C5;"><?= (int)$abha_stats['users_linked'] ?><span style="font-size:.78rem;color:#9ca3af;font-weight:400;"> / <?= (int)$abha_stats['users_total'] ?></span></div>
                                    <div style="height:5px;border-radius:3px;background:#e5e7eb;overflow:hidden;margin:6px 0;">
                                        <div style="height:100%;background:#0C74C5;width:<?= $user_link_pct ?>%;border-radius:3px;"></div>
                                    </div>
                                    <div style="font-size:.7rem;color:#6b7280;"><?= $user_link_pct ?>% linked &nbsp;·&nbsp; <?= (int)$abha_stats['users_verified'] ?> verified</div>
                                    <?php if ($abha_pending_req > 0): ?><a href="abha-management.php?tab=requests" style="font-size:.7rem;color:#ea580c;text-decoration:none;"><i class="fas fa-clock me-1"></i><?= $abha_pending_req ?> pending</a><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <!-- School Members ABHA -->
                        <div class="col-xl-4 col-md-3 col-sm-6">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                                <div class="card-body p-3">
                                    <div style="font-size:.72rem;font-weight:700;color:#6b7280;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">School Members</div>
                                    <div style="font-size:1.5rem;font-weight:700;color:#7c3aed;"><?= (int)$abha_school['members_linked'] ?><span style="font-size:.78rem;color:#9ca3af;font-weight:400;"> / <?= (int)$abha_school['members_total'] ?></span></div>
                                    <div style="height:5px;border-radius:3px;background:#e5e7eb;overflow:hidden;margin:6px 0;">
                                        <div style="height:100%;background:#7c3aed;width:<?= $memb_link_pct ?>%;border-radius:3px;"></div>
                                    </div>
                                    <div style="font-size:.7rem;color:#6b7280;"><?= $memb_link_pct ?>% linked &nbsp;·&nbsp; <?= (int)$abha_school['members_verified'] ?> verified</div>
                                    <a href="abha-management.php?portal=school" style="font-size:.7rem;color:#7c3aed;text-decoration:none;"><i class="fas fa-school me-1"></i>School ABHA →</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Quick Actions ── -->
                    <p class="section-heading">Quick Actions</p>
                    <div class="row g-3">
                        <?php
                        $actions = [
                            ['schools-list.php?status=Pending', 'bg-danger text-white', 'fas fa-school', 'Pending Schools', 'Review registrations'],
                            ['doctors-list.php', 'bg-primary text-white', 'fas fa-user-md', 'Doctors', 'Manage doctors'],
                            ['all-customers.php', 'bg-info text-white', 'fas fa-users', 'Patients', 'View patients'],
                            ['all-appointment.php', 'bg-success text-white', 'fas fa-calendar-check', 'Appointments', 'All appointments'],
                            ['add-doctor.php', 'bg-warning text-white', 'fas fa-user-plus', 'Add Doctor', 'Register new doctor'],
                            ['add-products.php', 'bg-secondary text-white', 'fas fa-box-open', 'Add Product', 'Add to inventory'],
                            ['new-leads.php', 'bg-dark text-white', 'fas fa-envelope', 'Inquiries', 'Customer inquiries'],
                            ['add-blog.php', 'bg-primary text-white', 'fas fa-pen', 'Blog Post', 'Publish new blog'],
                        ];
                        foreach ($actions as [$href, $cls, $icon, $title, $sub]): ?>
                            <div class="col-xl-3 col-md-3 col-sm-4 col-6">
                                <a href="<?= $href ?>" class="quick-action-card">
                                    <div class="qa-icon <?= $cls ?>"><i class="<?= $icon ?>"></i></div>
                                    <h6><?= $title ?></h6>
                                    <p><?= $sub ?></p>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ── Recent Orders + Recent Schools ── -->
                    <div class="row g-3 mt-1">
                        <!-- Recent Orders -->
                        <div class="col-lg-7">
                            <div class="card shadow-sm border-0 rounded-3 h-100">
                                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                                    <h6 class="fw-bold mb-0"><i class="fas fa-shopping-cart text-primary me-2"></i>Recent Orders</h6>
                                    <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Customer</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $r = mysqli_query($conn, "SELECT * FROM orders_new ORDER BY created_at DESC LIMIT 6");
                                                if (mysqli_num_rows($r) === 0): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted py-3">No orders yet</td>
                                                    </tr>
                                                <?php endif;
                                                while ($o = mysqli_fetch_assoc($r)):
                                                    $sc = ['completed' => 'success', 'pending' => 'warning', 'cancelled' => 'danger'][$o['status']] ?? 'secondary';
                                                ?>
                                                    <tr>
                                                        <td><small class="text-muted">#<?= $o['order_id'] ?? $o['id'] ?></small></td>
                                                        <td><?= htmlspecialchars(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?></td>
                                                        <td class="fw-semibold">₹<?= number_format($o['order_total'], 2) ?></td>
                                                        <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($o['status']) ?></span></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Schools -->
                        <div class="col-lg-5">
                            <div class="card shadow-sm border-0 rounded-3 h-100">
                                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                                    <h6 class="fw-bold mb-0"><i class="fas fa-school text-purple me-2" style="color:#7b2d8b"></i>Recent Schools</h6>
                                    <a href="schools-list.php" class="btn btn-sm btn-outline-secondary">View All</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>School</th>
                                                    <th>City</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $sr = mysqli_query($conn, "SELECT * FROM schools ORDER BY created_at DESC LIMIT 6");
                                                if (mysqli_num_rows($sr) === 0): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted py-3">No schools registered yet</td>
                                                    </tr>
                                                <?php endif;
                                                while ($s = mysqli_fetch_assoc($sr)):
                                                    $sc2 = ['Active' => 'success', 'Pending' => 'warning', 'Rejected' => 'danger', 'Inactive' => 'secondary'][$s['status']] ?? 'secondary';
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <a href="school-view.php?id=<?= $s['id'] ?>" class="text-decoration-none fw-semibold text-dark">
                                                                <?= htmlspecialchars(substr($s['school_name'], 0, 22)) ?><?= strlen($s['school_name']) > 22 ? '…' : '' ?>
                                                            </a>
                                                        </td>
                                                        <td><small class="text-muted"><?= htmlspecialchars($s['city']) ?></small></td>
                                                        <td><span class="badge bg-<?= $sc2 ?>"><?= $s['status'] ?></span></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include "footer.php"; ?>
</body>

</html>
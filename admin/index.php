<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: auth/login.php");
    exit();
}
include "db-conn.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Admin Panel | Dashboard</title>
  <!-- <link rel="icon" href="img/logo.png" type="image/png"> -->

  <!-- Bootstrap & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- ApexCharts -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.css">

  <!-- <link rel="stylesheet" href="css/style.css" />  -->
  <?php include "links.php"; ?>

  <style>
    /* Ensure full page height */
    html,
    body {
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    /* Wrapper to push content down */
    .wrapper {
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .main_content {
      flex: 1;
    }

    /* Ensure footer sticks at bottom */
    footer {
      position: relative;
      bottom: 0;
      background: #f8f9fa;
      padding: 15px 0;
      text-align: center;
      width: 100%;
    }

    /* Custom Card Styles */
    .stat-card {
      transition: all 0.3s ease;
      border-radius: 10px;
      border-left: 4px solid;
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .stat-card .card-icon {
      font-size: 2rem;
      opacity: 0.7;
    }

    .revenue-card {
      border-left-color: #4e73df;
    }

    .orders-card {
      border-left-color: #1cc88a;
    }

    .customers-card {
      border-left-color: #36b9cc;
    }

    .products-card {
      border-left-color: #f6c23e;
    }

    .chart-container {
      position: relative;
      height: 300px;
    }

    .activity-feed {
      max-height: 400px;
      overflow-y: auto;
    }

    .activity-item {
      border-left: 3px solid #4e73df;
      padding-left: 15px;
      margin-bottom: 15px;
    }

    .activity-time {
      font-size: 0.8rem;
      color: #6c757d;
    }

    .top-product-img {
      width: 40px;
      height: 40px;
      object-fit: cover;
      border-radius: 50%;
    }
  </style>
</head>

<body class="bg-light">

  <div class="wrapper">
    <?php
    include "header.php";
    ?>

    <section class="main_content dashboard_part">
      <div class="container-fluid g-0">
        <div class="row">
          <div class="col-lg-12 p-0">
            <?php include "top_nav.php"; ?>
          </div>
        </div>
      </div>

      <div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="row mt-4">
          <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center bg-white p-3 shadow rounded">
              <h3 class="m-0 fw-bold text-primary"><i class="fas fa-chart-line me-2"></i> Dashboard Overview</h3>
              <div class="d-flex">
                <div class="input-group me-2" style="width: 250px;">
                  <span class="input-group-text bg-white"><i class="fas fa-calendar-alt"></i></span>
                  <input type="text" class="form-control" id="dateRangePicker" placeholder="Select date range">
                </div>
                <button class="btn btn-primary"><i class="fas fa-download me-2"></i>Export</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mt-4">
          <?php
          // Get counts from database
          $total_revenue = 0;
          $total_orders = 0;
          $total_customers = 0;
          $total_products = 0;

          // Revenue (assuming we have orders data)
          $sql_orders = "SELECT SUM(order_total) as total FROM orders_new WHERE status = 'completed'";
          $res_orders = mysqli_query($conn, $sql_orders);
          if ($res_orders) {
            $row = mysqli_fetch_assoc($res_orders);
            $total_revenue = $row['total'] ? $row['total'] : 0;
          }

          // Total orders
          $sql_order_count = "SELECT COUNT(*) as count FROM orders_new";
          $res_order_count = mysqli_query($conn, $sql_order_count);
          if ($res_order_count) {
            $row = mysqli_fetch_assoc($res_order_count);
            $total_orders = $row['count'];
          }

          // Total customers
          $sql_cust = "SELECT COUNT(*) as count FROM users";
          $res_cust = mysqli_query($conn, $sql_cust);
          if ($res_cust) {
            $row = mysqli_fetch_assoc($res_cust);
            $total_customers = $row['count'];
          }

          // Total products
          $sql_pro = "SELECT COUNT(*) as count FROM products";
          $res_pro = mysqli_query($conn, $sql_pro);
          if ($res_pro) {
            $row = mysqli_fetch_assoc($res_pro);
            $total_products = $row['count'];
          }
          ?>



          <!-- Revenue Card -->
          <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card revenue-card shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col me-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                      Total Revenue</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?= number_format($total_revenue, 2) ?></div>
                    <div class="mt-2 mb-0 text-muted text-xs">
                      <span class="text-success me-2"><i class="fas fa-arrow-up me-1"></i> 12%</span>
                      <span>Since last month</span>
                    </div>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-dollar-sign card-icon text-primary"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Orders Card -->
          <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card orders-card shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col me-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                      Total Orders</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_orders ?></div>
                    <div class="mt-2 mb-0 text-muted text-xs">
                      <span class="text-success me-2"><i class="fas fa-arrow-up me-1"></i> 8%</span>
                      <span>Since last month</span>
                    </div>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-shopping-cart card-icon text-success"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Customers Card -->
          <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card customers-card shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col me-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                      Total Customers</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_customers ?></div>
                    <div class="mt-2 mb-0 text-muted text-xs">
                      <span class="text-danger me-2"><i class="fas fa-arrow-down me-1"></i> 2%</span>
                      <span>Since last month</span>
                    </div>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-users card-icon text-info"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Products Card -->
          <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card products-card shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col me-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                      Total Products</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_products ?></div>
                    <div class="mt-2 mb-0 text-muted text-xs">
                      <span class="text-success me-2"><i class="fas fa-arrow-up me-1"></i> 15%</span>
                      <span>Since last month</span>
                    </div>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-boxes card-icon text-warning"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
          <div class="col-12">
            <h4 class="mb-3 text-secondary"><i class="fas fa-bolt"></i> Quick Actions</h4>
          </div>

          <div class="col-lg-3 col-md-6 mb-4">
            <a href="add-products.php" class="card action-card shadow-sm border-0 text-center text-decoration-none">
              <div class="card-body">
                <div class="icon-circle bg-primary text-white mb-3">
                  <i class="fas fa-plus"></i>
                </div>
                <h5 class="card-title">Add Product</h5>
                <p class="text-muted small">Add new product to inventory</p>
              </div>
            </a>
          </div>

          <div class="col-lg-3 col-md-6 mb-4">
            <a href="add-blog.php" class="card action-card shadow-sm border-0 text-center text-decoration-none">
              <div class="card-body">
                <div class="icon-circle bg-success text-white mb-3">
                  <i class="fas fa-blog"></i>
                </div>
                <h5 class="card-title">Create Blog</h5>
                <p class="text-muted small">Publish new blog post</p>
              </div>
            </a>
          </div>

          <div class="col-lg-3 col-md-6 mb-4">
            <a href="new-leads.php" class="card action-card shadow-sm border-0 text-center text-decoration-none">
              <div class="card-body">
                <div class="icon-circle bg-info text-white mb-3">
                  <i class="fas fa-envelope"></i>
                </div>
                <h5 class="card-title">View Inquiries</h5>
                <p class="text-muted small">Check customer inquiries</p>
              </div>
            </a>
          </div>

          <div class="col-lg-3 col-md-6 mb-4">
            <a href="show-products.php" class="card action-card shadow-sm border-0 text-center text-decoration-none">
              <div class="card-body">
                <div class="icon-circle bg-warning text-white mb-3">
                  <i class="fas fa-boxes"></i>
                </div>
                <h5 class="card-title">Manage Products</h5>
                <p class="text-muted small">View and edit products</p>
              </div>
            </a>
          </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
          <!-- Revenue Chart -->
          <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
              <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Revenue Overview</h6>
                <div class="dropdown no-arrow">
                  <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuLink">
                    <li><a class="dropdown-item" href="#">This Year</a></li>
                    <li><a class="dropdown-item" href="#">This Month</a></li>
                    <li><a class="dropdown-item" href="#">This Week</a></li>
                    <li>
                      <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="#">Export Data</a></li>
                  </ul>
                </div>
              </div>
              <div class="card-body">
                <div class="chart-container">
                  <canvas id="revenueChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <!-- Pie Chart -->
          <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
              <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Revenue Sources</h6>
                <div class="dropdown no-arrow">
                  <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuLink">
                    <li><a class="dropdown-item" href="#">View Details</a></li>
                    <li><a class="dropdown-item" href="#">Export Data</a></li>
                  </ul>
                </div>
              </div>
              <div class="card-body">
                <div class="chart-container">
                  <canvas id="revenuePieChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                  <span class="me-2">
                    <i class="fas fa-circle text-primary"></i> Direct
                  </span>
                  <span class="me-2">
                    <i class="fas fa-circle text-success"></i> Social
                  </span>
                  <span class="me-2">
                    <i class="fas fa-circle text-info"></i> Referral
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Content Row -->
        <div class="row">
          <!-- Recent Orders -->
          <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
              <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
                <a href="orders.php" class="btn btn-sm btn-primary">View All</a>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead class="table-light">
                      <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $sql_recent_orders = "SELECT * FROM orders_new ORDER BY created_at DESC LIMIT 5";
                      $res_recent_orders = mysqli_query($conn, $sql_recent_orders);
                      while ($order = mysqli_fetch_assoc($res_recent_orders)) {
                        $status_class = '';
                        if ($order['status'] == 'completed')
                          $status_class = 'success';
                        elseif ($order['status'] == 'pending')
                          $status_class = 'warning';
                        else
                          $status_class = 'danger';

                        echo "<tr>
                          <td>#{$order['order_id']}</td>
                          <td>{$order['first_name']} {$order['last_name']}</td>
                          <td>" . date('M d, Y', strtotime($order['created_at'])) . "</td>
                          <td>₹{$order['order_total']}</td>
                          <td><span class='badge bg-{$status_class}'>{$order['status']}</span></td>
                          <td><a href='order-details.php?id={$order['id']}' class='btn btn-sm btn-outline-primary'>View</a></td>
                        </tr>";
                      }
                      ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Stats & Activity -->
          <div class="col-lg-4 mb-4">
            <!-- Quick Stats -->
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Stats</h6>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <div class="d-flex justify-content-between mb-1">
                    <span>New Customers</span>
                    <strong><?= $total_customers ?></strong>
                  </div>
                  <div class="progress">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 72%" aria-valuenow="72"
                      aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
                <div class="mb-3">
                  <div class="d-flex justify-content-between mb-1">
                    <span>Order Conversion</span>
                    <strong>42%</strong>
                  </div>
                  <div class="progress">
                    <div class="progress-bar bg-info" role="progressbar" style="width: 42%" aria-valuenow="42"
                      aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
                <div class="mb-3">
                  <div class="d-flex justify-content-between mb-1">
                    <span>Inventory</span>
                    <strong><?= $total_products ?> items</strong>
                  </div>
                  <div class="progress">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: 85%" aria-valuenow="85"
                      aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Recent Activity -->
            <div class="card shadow">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
              </div>
              <div class="card-body activity-feed">
                <?php
                // Get recent activities from different tables
                $activities = array();

                // Get recent blog activities
                $sql_blogs = "SELECT 'blog' as type, title as description, created_at FROM blogs ORDER BY created_at DESC LIMIT 2";
                $res_blogs = mysqli_query($conn, $sql_blogs);
                while ($blog = mysqli_fetch_assoc($res_blogs)) {
                  $activities[] = $blog;
                }

                // Get recent product activities
                $sql_products = "SELECT 'product' as type, pro_name as description, added_on as created_at FROM products ORDER BY added_on DESC LIMIT 2";
                $res_products = mysqli_query($conn, $sql_products);
                while ($product = mysqli_fetch_assoc($res_products)) {
                  $activities[] = $product;
                }

                // Get recent inquiries
                $sql_inquiries = "SELECT 'inquiry' as type, CONCAT('New inquiry from ', name) as description, created_at FROM inquiries ORDER BY created_at DESC LIMIT 2";
                $res_inquiries = mysqli_query($conn, $sql_inquiries);
                while ($inquiry = mysqli_fetch_assoc($res_inquiries)) {
                  $activities[] = $inquiry;
                }

                // Sort activities by date
                usort($activities, function ($a, $b) {
                  return strtotime($b['created_at']) - strtotime($a['created_at']);
                });

                // Display activities
                foreach (array_slice($activities, 0, 4) as $activity) {
                  $icon = '';
                  $color = '';
                  if ($activity['type'] == 'blog') {
                    $icon = 'fa-pen';
                    $color = 'primary';
                  } elseif ($activity['type'] == 'product') {
                    $icon = 'fa-box';
                    $color = 'success';
                  } else {
                    $icon = 'fa-envelope';
                    $color = 'info';
                  }

                  echo "<div class='activity-item'>
                    <div class='d-flex justify-content-between'>
                      <strong>{$activity['description']}</strong>
                      <span class='badge bg-{$color}'>" . ucfirst($activity['type']) . "</span>
                    </div>
                    <p class='activity-time mb-0'><i class='fas fa-clock me-1'></i>" . date('M j, Y g:i A', strtotime($activity['created_at'])) . "</p>
                  </div>";
                }
                ?>
              </div>
            </div>
          </div>
        </div>


      </div>
    </section>

    <footer>
      <?php include "footer.php"; ?>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.js"></script>

  <script>
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
          label: 'Revenue',
          data: [12000, 19000, 15000, 18000, 22000, 25000, 28000, 26000, 30000, 32000, 35000, 40000],
          backgroundColor: 'rgba(78, 115, 223, 0.05)',
          borderColor: 'rgba(78, 115, 223, 1)',
          pointBackgroundColor: 'rgba(78, 115, 223, 1)',
          pointBorderColor: '#fff',
          pointHoverBackgroundColor: '#fff',
          pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
          borderWidth: 2,
          tension: 0.3,
          fill: true
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: "rgb(255,255,255)",
            bodyFontColor: "#858796",
            titleMarginBottom: 10,
            titleFontColor: '#6e707e',
            titleFontSize: 14,
            borderColor: '#dddfeb',
            borderWidth: 1,
            xPadding: 15,
            yPadding: 15,
            displayColors: false,
            intersect: false,
            mode: 'index',
            caretPadding: 10,
            callbacks: {
              label: function (context) {
                var label = context.dataset.label || '';
                if (label) {
                  label += ': ';
                }
                if (context.parsed.y !== null) {
                  label += '₹' + context.parsed.y.toLocaleString();
                }
                return label;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function (value) {
                return '₹' + value.toLocaleString();
              }
            },
            grid: {
              color: "rgb(234, 236, 244)",
              zeroLineColor: "rgb(234, 236, 244)",
              drawBorder: false,
              borderDash: [2],
              zeroLineBorderDash: [2]
            }
          },
          x: {
            grid: {
              display: false,
              drawBorder: false
            },
            ticks: {
              padding: 20
            }
          }
        }
      }
    });

    // Revenue Pie Chart
    const revenuePieCtx = document.getElementById('revenuePieChart').getContext('2d');
    const revenuePieChart = new Chart(revenuePieCtx, {
      type: 'doughnut',
      data: {
        labels: ["Direct", "Referral", "Social"],
        datasets: [{
          data: [55, 30, 15],
          backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc'],
          hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf'],
          hoverBorderColor: "rgba(234, 236, 244, 1)",
        }],
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          tooltip: {
            backgroundColor: "rgb(255,255,255)",
            bodyFontColor: "#858796",
            borderColor: '#dddfeb',
            borderWidth: 1,
            xPadding: 15,
            yPadding: 15,
            displayColors: false,
            caretPadding: 10,
          },
          legend: {
            display: false
          },
        },
        cutout: '80%',
      },
    });

    // Initialize date range picker
    document.addEventListener('DOMContentLoaded', function () {
      // This would be replaced with actual date range picker initialization
      console.log('Date range picker would be initialized here');
    });
  </script>
</body>

</html>
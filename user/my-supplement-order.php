<?php
include_once "../config/connect.php";
include_once "../util/function.php";

$contact = contact_us();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="modinatheme">
  <meta name="description" content="">
  <title>REJUVENATE Digital Health</title>
  <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/magnific-popup.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/meanmenu.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/odometer.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/swiper-bundle.min.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/nice-select.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
</head>

<body>
  <?php include("../header.php") ?>
  <section class="contact-appointment-section section-padding fix ">
    <div class="container ">
      <div class="row mb-5">
        <div class="col-md-3">
          <?php include("sidebar.php") ?>
        </div>
        <div class="col-lg-9 ">
          <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
          <div class="profile-card shadow">
            <h4 class="mb-4">My Supplement Order</h4>
            <div class="row mt-4">
              <div class="col-md-12">
                <div class="booking-table">
                  <div class="table-responsive">
                    <table class="table">
                      <thead>
                        <tr>
                          <th scope="col">Sr. N</th>
                          <th scope="col"> Name</th>
                          <th scope="col">Date</th>
                          <th scope="col">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <th scope="row">1</th>
                          <td>Medicine Name</td>
                          <td>20/11/2025</td>
                          <td>Pending</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </section>
  <?php include("../footer.php") ?>
  <script>
    function toggleMenu() {
      document.getElementById("sidebarMenu").classList.toggle("show");
    }
  </script>
</body>

</html>
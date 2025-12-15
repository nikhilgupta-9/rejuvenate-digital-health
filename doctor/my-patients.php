<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");
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
  <style>
    .btn-upload {
      background-color: green;
      color: #fff;
      font-size: 12px;
    }

    .btn-upload:hover {
      background-color: #ccc;
    }

    .btn-delete {
      font-size: 12px;
      background-color: red;
      color: #fff
    }

    .btn-delete:hover {
      background-color: #ccc;
    }
  </style>
</head>

<body>
  <?php include("../header.php") ?>
  <section class="contact-appointment-section section-padding fix ">
    <div class="container ">
      <div class="row mb-5">
        <div class="col-md-3">
          <div class="sidebar" id="sidebarMenu">
            <div class="text-center info-content">
              <img src="assets/img/dummy.png" class="userd-image">
              <h5>Dr. Sanjay Chauhan</h5>
              <p>sunnychopra@gmail.com</p>
              <p>Phone: +91 7987987897</p>
              <a href="my-contact.php" class="btn btn-info btn-sm mb-3 mt-2">Edit Info</a>
            </div>

            <a href="<?= $site ?>doctor/doctor-dashboard.php" class="active">Dashboard</a>
            <a href="<?= $site ?>doctor/my-patients.php">My Patients</a>
            <a href="<?= $site ?>doctor/appointments.php">Appointments</a>
            <a href="<?= $site ?>doctor/patient-form.pdf"> Patients form</a>
            <a href="<?= $site ?>doctor/my-contact.php">Contact us</a>
            <a href="<?= $site ?>doctor/doctor-about.php">About us</a>
            <a href="#">Delete Account</a>

          </div>
        </div>
        <!-- Main Content -->
        <div class="col-lg-9 ">
          <!-- Mobile Toggle Button -->
          <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
          <div class="profile-card shadow">
            <h4 class="mb-4">My Patients</h4>
            <div class="row mt-4">
              <div class="booking-table">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th scope="col" style="background-color:#02c9b887; font-size: 14px;">Sr. N</th>
                        <th scope="col" style="background-color:#02c9b887; font-size: 14px;">Patients</th>
                        <th scope="col" style="background-color:#02c9b887; font-size: 14px;">Date</th>
                        <th scope="col" style="background-color:#02c9b887;font-size: 14px;">Contact Now</th>
                        <th scope="col" style="background-color:#02c9b887; font-size: 14px;">Status</th>
                        <th scope="col" style="background-color:#02c9b887; font-size: 14px;">View Documents</th>
                        <th scope="col" style="background-color:#02c9b887; font-size: 14px;">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr style="font-size:14px;">
                        <th scope="row">1</th>
                        <td>Ajay Kumar</td>
                        <td>20/11/2025</td>
                        <td><a href="tel:7897897897">7897897897</a></td>
                        <td style="color:red">Pending..</td>
                        <td>
                          <div class="d-flex"><a href="#"><img src="assets/img/pdf.png" style="height:25px;"></a><a href="#"><img src="assets/img/pdf.png" style="height:25px;"></a></div>
                        </td>
                        <td><a href="#" class="btn btn-upload">Upload File</a> <a href="#" class="btn btn-delete">Delete</a></td>
                      </tr>
                      <tr style="font-size:14px;">
                        <th scope="row">2</th>
                        <td>Sanjay Singh</td>
                        <td>20/11/2025</td>
                        <td><a href="tel:7897897897">7897897897</a></td>

                        <td style="color:green">Treatment Done</td>
                        <td>
                          <div class="d-flex"><a href="#"><img src="assets/img/pdf.png" style="height:25px;"></a><a href="#"><img src="assets/img/pdf.png" style="height:25px;"></a></div>
                        </td>
                        <td><a href="#" class="btn btn-upload">Upload File</a> <a href="#" class="btn btn-delete">Delete</a></td>
                      </tr>
                      <tr style="font-size:14px;">
                        <th scope="row">3</th>
                        <td>Rohit kumar</td>
                        <td>20/11/2025</td>
                        <td><a href="tel:7897897897">7897897897</a></td>
                        <td style="color:red">Pending..</td>
                        <td>
                          <div class="d-flex"><a href="#"><img src="assets/img/pdf.png" style="height:25px;"></a><a href="#"><img src="assets/img/pdf.png" style="height:25px;"></a></div>
                        </td>
                        <td><a href="#" class="btn btn-upload">Upload File</a> <a href="#" class="btn btn-delete">Delete</a></td>
                      </tr>
                      <tr style="font-size:14px;">
                        <th scope="row">4</th>
                        <td>Miss Savita Kumari</td>
                        <td>20/11/2025</td>
                        <td><a href="tel:7897897897">7897897897</a></td>
                        <td style="color:green">Treatment Done</td>
                        <td>
                          <div class="d-flex"><a href="#"><img src="assets/img/pdf.png" style="height:25px;"></a><a href="#"><img src="assets/img/pdf.png" style="height:25px;"></a></div>
                        </td>
                        <td><a href="#" class="btn btn-upload">Upload File</a> <a href="#" class="btn btn-delete">Delete</a></td>
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
  </section>
  <?php include("../footer.php") ?>
  <script>
    function toggleMenu() {
      document.getElementById("sidebarMenu").classList.toggle("show");
    }
  </script>
</body>

</html>
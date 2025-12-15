<?php
include_once( __DIR__ . "/../config/connect.php");
include_once( __DIR__ . "/../util/function.php");
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
    <?php include("../header.php")?>
        <section class="contact-appointment-section section-padding fix ">
           <div class="container ">
    <div class="row mb-5">
       <div class="col-md-3">
        <div class="sidebar" id="sidebarMenu">
            <div class="text-center info-content">
               <img src="<?= $site ?>assets/img/dummy.png" class="userd-image">
                <h5>Dr. Sanjay Chauhan</h5>
                <p>sunnychopra@gmail.com</p>
                <p>Phone: +91 7987987897</p>
                <a href="my-contact.php" class="btn btn-info btn-sm mb-3 mt-2">Edit Info</a>
            </div>

            <a href="<?= $site ?>doctor/doctor-dashboard.php" class="active">Dashboard</a>
            <a href="<?= $site ?>doctor/my-patients.php" >My Patients</a>
            <a href="<?= $site ?>doctor/appointments.php" >Appointments</a>
             <a href="<?= $site ?>doctor/patient-form.pdf" > Patients form</a>
            <a href="<?= $site ?>doctor/my-contact.php" >Contact us</a>
           <a href="<?= $site ?>doctor/doctor-about.php">About us</a>
            <a href="#" >Delete Account</a>
           
        </div>
        </div>
        <!-- Main Content -->
        <div class="col-lg-9 ">
            <!-- Mobile Toggle Button -->
          <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
            <div class="profile-card shadow">
                <h4 class="mb-4">Doctor Dashboard</h4>
                <div class="row mt-4">
                    <div class="col-md-4">
                      <div class="user_dash_box">
                          <a href="my-patients.php">
                         <img src="<?= $site ?>assets/img/docicon3.jpeg">
                          <h5>My Patients</h5>
                          </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                      <div class="user_dash_box">
                          <a href="appointments.php">
                         <img src="<?= $site ?>assets/img/docicon2.jpeg">
                          <h5>Appointments</h5>
                          </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                      <div class="user_dash_box">
                          <a href="my-supplement-order.php">
                         <img src="<?= $site ?>assets/img/docicon1.jpeg">
                          <h5>Patients form</h5>
                          </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                      <div class="user_dash_box">
                          <a href="my-contact.php">
                         <img src="<?= $site ?>assets/img/contact-us.png">
                          <h5>Contact us</h5>
                          </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                      <div class="user_dash_box">
                          <a href="doctor-about.php">
                         <img src="<?= $site ?>assets/img/about.png">
                          <h5>About us</h5>
                          </a>
                        </div>
                    </div>
                </div>
              </div>
           </div>
         </div>
       </div>
        </section>
       <?php include("../footer.php")?>
        <script>
    function toggleMenu() {
        document.getElementById("sidebarMenu").classList.toggle("show");
    }
</script>
    </body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="author" content="modinatheme">
        <meta name="description" content="">
        <title>REJUVENATE Digital Health</title>
        <link rel="stylesheet" href="assets/css/bootstrap.min.css">
        <link rel="stylesheet" href="assets/css/font-awesome.css">
        <link rel="stylesheet" href="assets/css/animate.css">
        <link rel="stylesheet" href="assets/css/magnific-popup.css">
        <link rel="stylesheet" href="assets/css/meanmenu.css">
        <link rel="stylesheet" href="assets/css/odometer.css">
        <link rel="stylesheet" href="assets/css/swiper-bundle.min.css">
        <link rel="stylesheet" href="assets/css/nice-select.css">
        <link rel="stylesheet" href="assets/css/main.css">
    <style>
   label {
    display: inline-block;
    font-size: 14px;
    font-weight: 600;
    color: #000;
}
    </style>
    </head>
    <body>
    <?php include("header.php")?>
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

            <a href="doctor-dashboard.php" >Dashboard</a>
            <a href="my-patients.php" >My Patients</a>
            <a href="appointments.php" >Appointments</a>
            <a href="#"> Patients form</a>
            <a href="my-contact.php" >Contact us</a>
            <a href="doctor-about.php" class="active">About us</a>
            <a href="#">Delete Account</a>
           
        </div>
        </div>
        <!-- Main Content -->
        <div class="col-lg-9 ">
            <!-- Mobile Toggle Button -->
          <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
            <div class="profile-card shadow">
                <h4 class="mb-4">About Doctor</h4>
                <div class="row mt-4">
                    <h3 style="font-size: 22px;
    padding: 13px 13px;
    color: #02c9b8;">Dr. Sanjay Chauhan</h3>
                  <div class="col-md-12">
      <div class="mb-3">
        <div class="doctor-des">Doctor profile</div>
        <p>MBBS, DGO</p>
      </div>
      <div class="mb-3">
        <div class="doctor-des">Area of expertise</div>
        <p>Female pelvic medicine and reconstructive surgery, Gynecologic oncology, Maternal-fetal medicine, Reproductive endocrinologists and infertility</p>
      </div>
      <div>
        <div class="doctor-des">Education</div>
        <p>M.B.B.S, DGO</p>
      </div>
    </div>
                </div>
              </div>
           </div>
         </div>
       </div>
        </section>
       <?php include("footer.php")?>
        <script>
    function toggleMenu() {
        document.getElementById("sidebarMenu").classList.toggle("show");
    }
</script>
    </body>
</html>
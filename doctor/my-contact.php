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
            <a href="#">Patients form</a>
            <a href="my-contact.php" class="active">Contact us</a>
            <a href="doctor-about.php">About us</a>
            <a href="#">Delete Account</a>
           
        </div>
        </div>
        <!-- Main Content -->
        <div class="col-lg-9 ">
            <!-- Mobile Toggle Button -->
          <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
            <div class="profile-card shadow">
                <h4 class="mb-4">My Contact Details</h4>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <label>Name</label>
                       <input type="text" class="form-control" placeholder="Dr. Sanjay Chauhan">
                    </div>
                    <div class="col-md-6">
                        <label>Mobile Number</label>
                           <input type="text" class="form-control" placeholder="7897897897">
                    </div>

                    <div class="col-md-6 mt-3">
                        <label>Email ID</label>
                           <input type="text" class="form-control" placeholder="sunnych@gmail.com">
                    </div>
                    <div class="col-md-6 mt-3">
                        <label>Gender</label>
                         <input type="text" class="form-control" placeholder="Male">
                    </div>
                    <div class="col-md-6 mt-3">
                        <label>Date of Birth</label>
                         <input type="date" class="form-control" placeholder="Male">
                    </div>
                     <div class="col-md-6 mt-3">
                        <label>Age</label>
                         <input type="text" class="form-control" placeholder="27">
                    </div>
                 
                      <div class="text-start mt-3">
                    <button class="btn btn-success">Save Changes</button>
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
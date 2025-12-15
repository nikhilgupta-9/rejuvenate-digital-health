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
            <a href="appointments.php" class="active">Appointments</a>
            <a href="#"> Patients form</a>
            <a href="my-contact.php" >Contact us</a>
            <a href="doctor-about.php">About us</a>
            <a href="#" >Delete Account</a>
           
        </div>
        </div>
        <!-- Main Content -->
        <div class="col-lg-9 ">
            <!-- Mobile Toggle Button -->
          <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
            <div class="profile-card shadow">
                <h4 class="mb-4">Appointments</h4>
                 <div class="row mt-4">
                   <div class="booking-table">
                     <div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <tr>
        <th scope="col" style="background-color:#02c9b887; font-size: 14px;" >Sr. N</th>
        <th scope="col" style="background-color:#02c9b887; font-size: 14px;">Patients</th>
        <th scope="col" style="background-color:#02c9b887; font-size: 14px;">Date</th>
        <th scope="col" style="background-color:#02c9b887;font-size: 14px;">Contact Now</th>
        <th scope="col" style="background-color:#02c9b887; font-size: 14px;">Status</th>
      
      </tr>
    </thead>
    <tbody>
      <tr style="font-size:14px;">
        <th scope="row">1</th>
        <td>Ajay Kumar</td>
        <td>20/11/2025</td>
         <td><a href="tel:7897897897">7897897897</a></td>
          <td style="color:red">Pending..</td>
         
        </tr>
         <tr style="font-size:14px;">
        <th scope="row">2</th>
        <td>Sanjay Singh</td>
        <td>20/11/2025</td>
        <td><a href="tel:7897897897">7897897897</a></td>
             
        <td style="color:green">Connected</td>
             
      </tr>
         <tr style="font-size:14px;">
        <th scope="row">3</th>
        <td>Rohit kumar</td>
        <td>20/11/2025</td>
        <td><a href="tel:7897897897">7897897897</a></td>
    <td style="color:red">Pending..</td>
      </tr>
         <tr style="font-size:14px;">
        <th scope="row">4</th>
        <td>Miss Savita Kumari</td>
        <td>20/11/2025</td>
        <td><a href="tel:7897897897">7897897897</a></td>
         <td style="color:green">Connected</td>
              
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
       <?php include("footer.php")?>
        <script>
    function toggleMenu() {
        document.getElementById("sidebarMenu").classList.toggle("show");
    }
</script>
    </body>
</html>
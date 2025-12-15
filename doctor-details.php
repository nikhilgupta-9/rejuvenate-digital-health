<?php
include_once "config/connect.php";
include_once "util/function.php";


$profile = getDoctorsBySlug();

if ($profile['is_verified'] == 1) {
  $verified = "Verified Profile";
  $verified_class = "verified-badge";
} else {
  $verified = "Unverified Profile";
  $verified_class = "unverified-badge";
}
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
<style>
  .verified-badge {
    background-color: #e9f9f0;
    color: #009f4d;
    border: 1px solid #009f4d;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    margin-left: 10px;
  }

  .unverified-badge {
    background-color: #f5cec8ff;
    color: #9f0b00ff;
    border: 1px solid #9f0b00ff;
    ;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    margin-left: 10px;
  }
</style>

<body>
  <?php include("header.php") ?>
  <section class="service-details-section section-padding  pt-0 pb-5">

    <div class="container my-1">
      <div class="con-line ">
        <ul>
          <li><a href="<?= $site ?>">Home</a></li>
          <li><a href="e-cardiology.php">E-Cardiology</a></li>
          <li><a href="#">Profile</a></li>
        </ul>
      </div>
      <!-- Header Section -->
      <div class="profile-header d-flex align-items-center flex-wrap justify-content-between">
        <div class="d-flex align-items-center flex-wrap">
          <img src="<?= $site ?>admin/<?= $profile['profile_image'] ?>" alt="Doctor">
          <div class="ms-3">
            <h4><?= $profile['name'] ?>
              <span class="<?= $verified_class ?>">
                <i class="bi bi-check-circle"></i> <?= $verified ?>
              </span>
            </h4>
            <p class="mb-1"><?= $profile['degrees'] ?></p>
            <p class="mb-0 small">
              <?= $profile['short_bio'] ?><br>
              (<?= $profile['experience_years'] ?> Years Experience Overall)
            </p>
          </div>
        </div>
        <a href="<?= $site ?>contact/" class="appointment-btn">Book an Appointment</a>
      </div>

      <!-- Body Section -->
      <div class="profile-body row mt-0">
        <!-- Rating -->
        <?php
        $rating = floatval($profile['rating']); // e.g., 4.2

        // Generate star display
        $fullStars = floor($rating);
        $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
        $emptyStars = 5 - ($fullStars + $halfStar);

        $stars = str_repeat("★", $fullStars);
        $stars .= $halfStar ? "☆" : "";
        $stars .= str_repeat("✩", $emptyStars);
        ?>

        <div class="col-md-3 rating-box mb-3 mb-md-0">
          <p class="fw-semibold">Overall Rating</p>
          <h2><?= number_format($rating, 1) ?></h2>
          <div class="stars" style="font-size: 22px; color: #ffb400;">
            <?= $stars ?>
          </div>
        </div>


        <!-- Profile Info -->
        <div class="col-md-6">
          <div class="mb-3">
            <div class="doctor-des">Doctor profile</div>
            <p><?= $profile['long_bio'] ?></p>
          </div>
          <div class="mb-3">
            <div class="doctor-des">Area of expertise</div>
            <p><?= $profile['area_of_expertise'] ?></p>
          </div>
          <div>
            <div class="doctor-des">Education</div>
            <p><?= $profile['education'] ?></p>
          </div>
        </div>

        <div class="col-md-3">
          <div class="specialization-box">
            <h6 class="fw-bold"><img src="<?= $site ?>assets/img/specialisation.png"> Specialization</h6>
            <p><?= $profile['specialization'] ?></p>
            <h6 class="fw-bold mt-3"><img src="<?= $site ?>assets/img/languages.png"> Languages known:</h6>
            <p><?= $profile['languages'] ?></p>
            <h6 class="fw-bold mt-3"><img src="<?= $site ?>assets/img/registration.png"> Consultation Fees:</h6>
            <p>₹<?= $profile['consultation_fee'] ?></p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php include("footer.php") ?>
</body>

</html>
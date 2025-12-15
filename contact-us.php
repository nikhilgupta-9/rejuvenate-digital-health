<?php
include_once "config/connect.php";
include_once "util/function.php";

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
    <?php include("header.php")?>
         <!-- Breadcrumb Section Start -->
        <div class="breadcrumb-wrapper bg-cover" style="background-image: url('<?= $site?>assets/img/inner/breadcrumb-img.jpg');">
            <div class="container">
                <div class="page-heading">
                   <div class="breadcrumb-items-area">
                         <div class="breadcrumb-sub-title">
                            <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">Contact Us</h1>
                        </div>
                        <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                            <li>
                                <a href="<?= $site?> ?>">
                                Home
                                </a>
                            </li>
                            <li>
                                //
                            </li>
                            <li>
                                Contact us
                            </li>
                        </ul>
                   </div>
                </div>
            </div>
        </div>

         <!-- Contact-Appointment Section5 Start -->
        <section class="contact-appointment-section section-padding fix">
            <div class="container">
                <div class="contact-appointment-wrapper-5">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="contact-appointment-left-item">
                                <div class="contact-appointment-image wow img-custom-anim-left" data-wow-duration="1.3s" data-wow-delay="0.3s">
                                    <img src="<?= $site?>assets/img/inner/contact/01.jpg" alt="img">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="contact-appointment-box">
                               <h3>Book An Appointment</h3>
                               <form action="#" id="contactForm">
                                 <div class="row g-4">
                                    <div class="col-lg-6 wow fadeInUp" data-wow-delay=".3s">
                                        <div class="form-clt">
                                            <span>Name</span>
                                            <input type="text" name="fname" id="email17" placeholder="Your Name">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 wow fadeInUp" data-wow-delay=".5s">
                                        <div class="form-clt">
                                            <span>Email</span>
                                            <input type="text" name="email" id="name" placeholder="Your email">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 wow fadeInUp" data-wow-delay=".3s">
                                        <div class="form-clt">
                                            <span>Phone</span>
                                            <input type="text" name="phone" id="name15" placeholder="Your phone">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 wow fadeInUp" data-wow-delay=".5s">
                                        <div class="form-clt">
                                            <span>Department</span>
                                                <div class="form">
                                                <select class="single-select w-100" name="subject">
                                                    <option value="1">Your department</option>
                                                    <?php
                                                    foreach ($department as $dep){
                                                    ?>
                                                    <option value="<?= $dep['categories'] ?>"> <?= $dep['categories'] ?></option>
                                                    <?php } ?>
                                                    <!-- <option value="CareSync Desk">CareSync Desk</option> -->
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12 wow fadeInUp" data-wow-delay=".3s">
                                        <div class="form-clt">
                                            <span>Your message</span>
                                            <textarea name="message" id="message" placeholder="Write your message..."></textarea>
                                        </div>
                                    </div>
                                    <div class="col-lg-12 wow fadeInUp" data-wow-delay=".3s">
                                        <button type="submit" class="theme-btn">
                                            <i class="far fa-chevron-right"></i>
                                            Make Your Appointment
                                        </button>
                                    </div>

                                    <div id="contactMessage"  class="alert alert-success"></div>
                                </div>
                            </form>
                          </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact-info Section Start -->
        <section class="contact-info-section section-padding pt-5 pb-5">
            <div class="container">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="contact-info-box-items">
                            <div class="icon">
                                <i class="far fa-phone-alt"></i>
                            </div>
                            <div class="content">
                                <h6>Call Us</h6>
                                <a href="tel:+91-<?= $contact['phone']?>">+91-<?= $contact['phone']?></a><br>
                                <a href="tel:+91-<?= $contact['wp_number']?>">+91-<?= $contact['wp_number']?></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="contact-info-box-items">
                            <div class="icon">
                                <i class="far fa-envelope"></i>
                            </div>
                            <div class="content">
                                <h6>Send Email</h6>
                                <a href="mailto:<?= $contact['email']?>"><?= $contact['email']?></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="contact-info-box-items">
                            <div class="icon">
                                <i class="fal fa-map-marker-alt"></i>
                            </div>
                            <div class="content">
                                <h6>Location</h6>
                                <?= $contact['address']?>
                            </div>
                        </div>
                    </div>
                </div>
                <br>
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d52568.360774553505!2d77.51522754361174!3d29.96362704058269!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x390eea921f841f45%3A0x39baf780903811f!2sSaharanpur%2C%20Uttar%20Pradesh!5e1!3m2!1sen!2sin!4v1762346262363!5m2!1sen!2sin" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </section>

       <?php include("footer.php")?>
    </body>
</html>
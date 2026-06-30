<?php
include "header.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitemap | DRM and Company</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/responsive.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&amp;display=swap"
        rel="stylesheet">
    <link href="assets/css/color-switcher-design.css" rel="stylesheet">
    <link id="theme-color-file" href="assets/css/color-themes/default-color.css" rel="stylesheet">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <style>
        .sitemap-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .sitemap-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .sitemap-header h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .sitemap-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .sitemap-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .sitemap-section {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .sitemap-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .sitemap-section h2 {
            color: #3498db;
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f1f1;
        }

        .sitemap-section ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sitemap-section li {
            margin-bottom: 12px;
        }

        .sitemap-section a {
            color: #34495e;
            text-decoration: none;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
        }

        .sitemap-section a:hover {
            color: #3498db;
        }

        .sitemap-section a:before {
            content: "→";
            margin-right: 10px;
            color: #3498db;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .sitemap-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="sitemap-container">
        <div class="sitemap-header">
            <h1>Website Sitemap</h1>
            <p>Navigate through our website with ease using our comprehensive sitemap</p>
        </div>

        <div class="sitemap-grid">
            <div class="sitemap-section">
                <h2>Main Pages</h2>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="services.php">Services</a></li>
                    <li><a href="portfolio.php">Portfolio</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                </ul>
            </div>

            <div class="sitemap-section">
                <h2>Services</h2>
                <ul>
                    <li><a href="service1.php">Service 1</a></li>
                    <li><a href="service2.php">Service 2</a></li>
                    <li><a href="service3.php">Service 3</a></li>
                    <li><a href="service4.php">Service 4</a></li>
                    <li><a href="service5.php">Service 5</a></li>
                </ul>
            </div>

            <div class="sitemap-section">
                <h2>Resources</h2>
                <ul>
                    <li><a href="blog.php">Blog</a></li>
                    <li><a href="case-studies.php">Case Studies</a></li>
                    <li><a href="whitepapers.php">Whitepapers</a></li>
                    <li><a href="faq.php">FAQ</a></li>
                    <li><a href="testimonial.php">Testimonials</a></li>
                </ul>
            </div>

            <div class="sitemap-section">
                <h2>Company</h2>
                <ul>
                    <li><a href="team.php">Our Team</a></li>
                    <li><a href="terms-and-condition.php">Terms and Conditions</a></li>
                    <li><a href="shipping.php">Shipping Policy</a></li>
                    <li><a href="return-policy.php">Return Policy</a></li>
                    <li><a href="privacy-policy.php">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
    </div>
</body>

</html>
<?php
include "footer.php";
?>
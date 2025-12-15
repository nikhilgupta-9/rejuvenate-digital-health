<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../config/connect.php";

// Fetch contact details
$query = "SELECT * FROM `contacts`";
$sql_query = $conn->query($query);

if ($sql_query && $sql_query->num_rows > 0) {
    $result = $sql_query->fetch_assoc();
    $phone1 = $result['phone'] ?? '';
    $phone2 = $result['wp_number'] ?? '';
    $phone3 = $result['telephone'] ?? '';
    $address = $result['address'] ?? '';
    $address2 = $result['address2'] ?? '';
    $email = $result['email'] ?? '';
    $email2 = $result['contact_email'] ?? '';
    $fb = $result['facebook'] ?? '';
    $insta = $result['instagram'] ?? '';
    $x = $result['twitter'] ?? '';
    $linkdin = $result['linkdin'] ?? '';
    $map = $result['map'] ?? '';
} else {
    echo "No contact records found.";
}

//fetch logo details
$sql_logo = "SELECT * FROM `logos` order by id desc limit 1";
$re_logo = mysqli_query($conn, $sql_logo);
if (mysqli_num_rows($re_logo)) {
    $row = mysqli_fetch_assoc($re_logo);

    $logo_path = "admin/" . $row['logo_path'];
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['fname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    $sql = "INSERT INTO `inquiries`(`name`, `email`, `phone`, `subject`, `message`, `created_at`) VALUES (?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $phone, $subject, $message);
        $result = mysqli_stmt_execute($stmt);

        if ($result) {
            echo "<script>alert('Message sent successfully, we will connect soon!');</script>";
        } else {
            echo "<script>alert('Error: Unable to send message.');</script>";
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "<script>alert('Database error: Unable to prepare statement.');</script>";
    }

    mysqli_close($conn);
}

$mail = new PHPMailer(true);

try {
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com'; // Your mail server
    $mail->SMTPAuth = true;
    $mail->Username = 'no-reply@web2techsolutions.com'; // Your email
    $mail->Password = '8@MGB+XSAsx'; // Your email password (should be stored securely!)
    $mail->Port = 465;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->CharSet = 'UTF-8';

    // Validate required fields
    if (empty($_POST["fname"]) || empty($_POST["email"]) || empty($_POST["message"])) {
        echo "<script>alert('Please fill all required fields.'); window.location.href='contact.php';</script>";
        exit();
    }

    // Validate email
    if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email address.'); window.location.href='contact.php';</script>";
        exit();
    }

    // Sender & Recipient
    $mail->setFrom('info@sapphiredecor.in', 'Softech Systems India Pvt Ltd.');
    $mail->addReplyTo($_POST["email"], $_POST["fname"]);
    $mail->addAddress($email); // Main Admin Email
    $mail->addAddress("iamnikhilgupta9@gmail.com"); // Forwarded Email

    // Email Subject & Body
    $subject = !empty($_POST["subject"]) ? $_POST["subject"] : "No Subject";
    $phone = !empty($_POST["phone"]) ? $_POST["phone"] : "Not Provided";

    $mail->Subject = "New Inquiry - Softech Systems India Pvt Ltd." ;
    $mail->isHTML(true);

   $mail->Body = "
<html>
<head>
    <style>
        body { 
            font-family: 'Poppins', Arial, sans-serif; 
            background: #f5f9fc; 
            color: #333; 
            padding: 0;
            margin: 0;
            line-height: 1.6;
        }
        .email-container { 
            max-width: 650px; 
            margin: 0 auto; 
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .email-header { 
            background: linear-gradient(135deg, #0066cc 0%, #004080 100%);
            padding: 35px 20px;
            text-align: center;
            color: #fff;
        }
        .email-header img { 
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
        }
        .email-header h1 {
            font-size: 28px;
            margin: 0;
            font-weight: 600;
            color: #fff;
            letter-spacing: 0.5px;
        }
        .email-header p {
            font-size: 16px;
            opacity: 0.9;
            margin-top: 10px;
        }
        .email-content {
            padding: 30px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .data-table th {
            background: #0066cc;
            color: #fff;
            padding: 14px 20px;
            text-align: left;
            font-weight: 500;
            font-size: 15px;
        }
        .data-table td {
            padding: 14px 20px;
            border-bottom: 1px solid #e9f0f5;
            font-size: 15px;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fbfe;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .message-content {
            background: #f8fbfe;
            padding: 20px;
            border-radius: 6px;
            margin-top: 25px;
            border-left: 4px solid #0066cc;
        }
        .email-footer {
            background: #1a2b3d;
            color: #fff;
            padding: 30px 20px;
            text-align: center;
            font-size: 14px;
        }
        .cta-button {
            display: inline-block;
            background: #00a0e9;
            color: #fff;
            padding: 14px 30px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            margin: 20px 0;
            transition: all 0.3s;
            font-size: 15px;
            box-shadow: 0 4px 8px rgba(0,160,233,0.2);
        }
        .cta-button:hover {
            background: #0088c7;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,160,233,0.3);
        }
        .social-links {
            margin: 25px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 8px;
            color: #fff;
            background: rgba(255,255,255,0.15);
            width: 40px;
            height: 40px;
            line-height: 40px;
            border-radius: 50%;
            text-align: center;
            transition: all 0.3s;
        }
        .social-links a:hover {
            background: #00a0e9;
            transform: translateY(-3px);
        }
        .urgency-badge {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            margin-left: 10px;
            vertical-align: middle;
        }
        .footer-links {
            margin: 15px 0;
        }
        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            margin: 0 10px;
            font-size: 13px;
            transition: color 0.3s;
        }
        .footer-links a:hover {
            color: #00a0e9;
        }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='email-header'>
            <img src='<?=$site?>/<?= $logo_path ?>' alt='Softech Systems India Pvt Ltd.'>
            <h1>New Softech Systems India Pvt Ltd. <span class='urgency-badge'>ACTION REQUIRED</span></h1>
            <p>Please respond to this inquiry within 24 hours</p>
        </div>
        
        <div class='email-content'>
            <table class='data-table'>
                <tr>
                    <th>Contact Name</th>
                    <td>{$_POST['fname']}</td>
                </tr>
                <tr>
                    <th>Phone Number</th>
                    <td>{$phone}</td>
                </tr>
                <tr>
                    <th>Email Address</th>
                    <td>{$_POST['email']}</td>
                </tr>
                <tr>
                    <th>Inquiry Type</th>
                    <td>{$subject}</td>
                </tr>
               
            </table>
            
            <div class='message-content'>
                <h3 style='margin-top: 0; color: #0066cc;'>Message Details:</h3>
                <p>{$_POST['message']}</p>
            </div>
            
            <div style='text-align: center; margin-top: 30px;'>
                <a href='mailto:{$_POST['email']}' class='cta-button'>Reply to Inquiry</a>
            </div>
        </div>
        
        <div class='email-footer'>
            <div class='social-links'>
                <a href='<?=$fb?>'><img src='https://cdn-icons-png.flaticon.com/24/733/733547.png' alt='Facebook'></a>
                <a href='<?=$x?>'><img src='https://cdn-icons-png.flaticon.com/24/733/733579.png' alt='Twitter'></a>
                <a href='<?=$insta?>'><img src='https://cdn-icons-png.flaticon.com/24/2111/2111463.png' alt='Instagram'></a>
                <a href='<?=$linkdin?>'><img src='https://cdn-icons-png.flaticon.com/24/733/733561.png' alt='LinkedIn'></a>
                
            </div>
            
            <p style='margin-bottom: 5px;'>This inquiry was submitted through <strong>Softech Systems India Pvt Ltd.</strong> contact form.</p>
            <p style='margin-top: 5px; opacity: 0.8;'>Our team typically responds within 2 business hours.</p>
            
            <p style='margin-top: 25px; color: rgba(255,255,255,0.6); font-size: 12px; line-height: 1.5;'>
                &copy; " . date("Y") . " Softech Systems India Pvt Ltd. All Rights Reserved.<br>
                <span style='font-size: 11px;'>This email contains confidential information intended only for the recipient.</span>
            </p>
        </div>
    </div>
</body>
</html>
";
    // Don't forget to set the email content type to HTML
    $mail->isHTML(true);

    // Send Email
    $mail->send();
    echo "<script>alert('Message sent successfully!'); window.location.href='".$site."contact-us.php';</script>";
    exit();

} catch (Exception $e) {
    echo "Error: {$mail->ErrorInfo}";
}
?>


<?php
include_once( __DIR__ . "/../config/connect.php");
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once( __DIR__ . "/../vendor/autoload.php"); // Adjust path based on your project structure

// get category 
function get_category() {
    global $conn;

    $sql = "SELECT * FROM `categories` WHERE status = 1";
    $res = mysqli_query($conn, $sql);

    $categories = [];

    if ($res && mysqli_num_rows($res) > 0) {
        while ($row = mysqli_fetch_assoc($res)) {
            $categories[] = $row;
        }
    }

    return $categories;
}

// get abouts 
function fetch_about()
{
    global $conn;

    $sql = "SELECT * FROM `about_us`";
    $sql_query = $conn->query($sql);

    if ($sql_query && $sql_query->num_rows > 0) {
        $result = $sql_query->fetch_assoc();

        return [
            'title' => $result['title'] ?? '',
            'content' => $result['content'] ?? '',
            'image' => $result['image_url'] ?? ''
        ];
    } else {
        return [
            'title' => '',
            'content' => 'No about us record found.',
            'image' => ''
        ];
    }
}

// logo 
function get_header_logo()
{
    global $conn;

    $sql_logo = "SELECT * FROM `logos` where `location` = 'header' order by id desc limit 1";
    $re_logo = mysqli_query($conn, $sql_logo);
    if (mysqli_num_rows($re_logo)) {
        $row = mysqli_fetch_assoc($re_logo);

        return "admin/uploads/" . $row['logo_path'];
    }
}


function get_footer_logo()
{
    global $conn;

    $sql_logo = "SELECT * FROM `logos` where `location` = 'footer' order by id desc limit 1";
    $re_logo = mysqli_query($conn, $sql_logo);
    if (mysqli_num_rows($re_logo)) {
        $row = mysqli_fetch_assoc($re_logo);

        return "admin/uploads/" . $row['logo_path'];
    }
}
// logo end 



// fetch banners 
function fetch_banner()
{
    global $conn;

    $banners = [];
    $sql_banner = "SELECT * FROM `banners` WHERE status = 0";
    $res_banner = mysqli_query($conn, $sql_banner);

    if ($res_banner) {
        while ($row_banner = mysqli_fetch_assoc($res_banner)) {
            $banners[] = $row_banner;
        }
    }

    return $banners;
}


// get contact us page 
function contact_us()
{
    global $conn;

    if (!$conn || !$conn->ping()) {
        // Connection is not available or already closed
        return null;
    }

    $query = "SELECT * FROM `contacts` LIMIT 1";
    $sql_query = $conn->query($query);

    if ($sql_query && $sql_query->num_rows > 0) {
        $result = $sql_query->fetch_assoc();

        return [
            'phone' => $result['phone'] ?? '',
            'wp_number' => $result['wp_number'] ?? '',
            'telephone' => $result['telephone'] ?? '',
            'address' => $result['address'] ?? '',
            'address2' => $result['address2'] ?? '',
            'email' => $result['email'] ?? '',
            'contact_email' => $result['contact_email'] ?? '',
            'facebook' => $result['facebook'] ?? '',
            'instagram' => $result['instagram'] ?? '',
            'twitter' => $result['twitter'] ?? '',
            'linkdin' => $result['linkdin'] ?? '',
            'map' => $result['map'] ?? ''
        ];
    }

    return null; // Or return [] if you prefer
}


// get gallery images 
function get_gallery()
{
    global $conn;

    $sql = "SELECT * FROM `gallery`";
    $sql_query = $conn->query($sql);

    $images = [];

    if ($sql_query && $sql_query->num_rows > 0) {
        while ($result = $sql_query->fetch_assoc()) {
            $images[] = "admin/" . ($result['image_path'] ?? '');
        }
    }

    return $images; // returns an empty array if no records
}

// fetch brand from products 
function get_brands(): array
{
    global $conn;

    $sql = "SELECT DISTINCT brand_name FROM products WHERE status = 1";
    $result = mysqli_query($conn, $sql);

    $brands = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $brands[] = $row['brand_name']; // store only the string
        }
    }

    return $brands;
}


// get products for home page
function get_product(): array
{
    global $conn;

    $sql_pro = "SELECT * FROM `products` WHERE status = 1 ";
    $res_pro = mysqli_query($conn, $sql_pro);

    $products = [];

    if ($res_pro) {
        while ($row_pro = mysqli_fetch_assoc($res_pro)) {
            $products[] = $row_pro;
        }
    }

    return $products; // returns an array of 6 latest active products
}


function get_online_book($limit): array
{
    global $conn;

    $sql_pro = "SELECT * FROM `products` WHERE `pro_cate` = 87920 AND status = 1 limit $limit";
    $res_pro = mysqli_query($conn, $sql_pro);

    $products = [];

    if ($res_pro) {
        while ($row_pro = mysqli_fetch_assoc($res_pro)) {
            $products[] = $row_pro;
        }
    }

    return $products; // returns an array of 6 latest active products
}

function get_sub_category()
{
    global $conn;
    $sub_category = [];

    // Use prepared statement to prevent SQL injection
    $sql = "SELECT * FROM `sub_categories` where `parent_id` = 20873 AND `status` = 1 order by `id`"; // Assuming it's a boolean/bit field

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        // Log error or handle it appropriately
        error_log("Database error: " . mysqli_error($conn));
        return $sub_category; // Return empty array on error
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $sub_category[] = $row;
    }

    return $sub_category;
}

// ONLINE E SERVICES 

function get_online_eServices()
{
    global $conn;
    $sub_category = [];

    // Use prepared statement to prevent SQL injection
    $sql = "SELECT * FROM `sub_categories` where `parent_id` = 86847 AND `status` = 1 order by `id`"; // Assuming it's a boolean/bit field

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        // Log error or handle it appropriately
        error_log("Database error: " . mysqli_error($conn));
        return $sub_category; // Return empty array on error
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $sub_category[] = $row;
    }

    return $sub_category;
}

function get_doctor_byDepartment()
{
    global $conn;

    if (!isset($_GET['alias'])) {
        header("Location: index.php");
        exit();
    }

    $alias = mysqli_real_escape_string($conn, $_GET['alias']);
    $doctors = [];

    $sql = "SELECT d.*, dd.doctor_id, dd.category_id, 
            sc.categories as depart_name, sc.cate_id, 
            sc.slug_url as department_slug
            FROM doctors d
            LEFT JOIN doctor_departments dd 
                ON d.id = dd.doctor_id
            LEFT JOIN sub_categories sc 
                ON dd.category_id = sc.cate_id
            WHERE sc.slug_url = '$alias'
            AND d.status = 'Active'
            ORDER BY d.id";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        error_log("Database error: " . mysqli_error($conn));
        return $doctors;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $doctors[] = $row;
    }

    return $doctors;
}


// fetching trending product 
function get_trending_product(){
    global $conn;

    $sql = "SELECT * FROM `products` where `trending` = 1 order by id desc limit 8";
    $res = mysqli_query($conn, $sql);

    
    if (!$res) {
        header("Location: 500.php"); 
                exit(); 
    }

    $trendingProducts = []; // ✅ Initialize the array before using
    while ($row = mysqli_fetch_assoc($res)) {
        $trendingProducts[] = $row;
    }

    return $trendingProducts; // ✅ Return the result
}

// blog fetch for home page 
function get_blog_home()
{
    global $conn;

    $sql_blog = "SELECT * FROM `blogs` limit 3";
    $res_blog = mysqli_query($conn, $sql_blog);

    if (!$res_blog) {
        header("Location: 500.php"); // ✅ Remove spaces around colon
        exit(); // ✅ Always add exit after header redirect
    }

    $blog = []; // ✅ Initialize the array before using
    while ($row = mysqli_fetch_assoc($res_blog)) {
        $blog[] = $row;
    }

    return $blog; // ✅ Return the result
}


// blog fetch for blog page 
function get_blog()
{
    global $conn;

    $sql_blog = "SELECT * FROM `blogs` ";
    $res_blog = mysqli_query($conn, $sql_blog);

    if (!$res_blog) {
        header("Location: 500.php"); // ✅ Remove spaces around colon
        exit(); // ✅ Always add exit after header redirect
    }

    $blog = []; // ✅ Initialize the array before using
    while ($row = mysqli_fetch_assoc($res_blog)) {
        $blog[] = $row;
    }

    return $blog; // ✅ Return the result
}

// blog details fetch 
function fetch_blog_detail($slug)
{
    global $conn;
    global $site;

    $blog_slug = mysqli_real_escape_string($conn, $slug);
    // die($slug);

    $sql_blog = "SELECT * FROM `blogs` WHERE `slug_url` = '$blog_slug' LIMIT 1";
    $res_blog = mysqli_query($conn, $sql_blog);

    if (!$res_blog) {
        header("Location: 500.php");
        exit();
    }

    $blog_det = mysqli_fetch_assoc($res_blog);

    if (!$blog_det) {
        header("Location: ".$site."404.php");
        exit();
    }

    return $blog_det;
}

// product page fetch product 
function fetch_product_page()
{
    global $conn;

    if (!isset($_GET['alias'])) {
        header("Location: index.php");
        exit();
    }

    $alias = mysqli_real_escape_string($conn, $_GET['alias']);

    // Get subcategory information
    $sql1 = "SELECT * FROM `sub_categories` WHERE `slug_url` = '$alias'";
    $res = mysqli_query($conn, $sql1);

    if (!$res || mysqli_num_rows($res) == 0) {
        header("Location: 404.php");
        exit();
    }

    $sub_cat = mysqli_fetch_assoc($res);
    $pro_sub_cate = $sub_cat['cate_id'];
    $_SESSION['sub_cat_name'] = $sub_cat['categories'];
    $meta_title = $sub_cat['meta_title'];
    $meta_key = $sub_cat['meta_key'];
    $meta_desc = $sub_cat['meta_desc'];

}

function fetch_product_details()
{
    global $conn;

    if (!isset($_GET['alias']) || empty($_GET['alias'])) {
        die("Invalid product URL. Alias parameter is missing.");
    }

    $alias = mysqli_real_escape_string($conn, $_GET['alias']);

    $sql = "SELECT * FROM `products` WHERE `slug_url` = '$alias' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        return [
            'pro_name' => $row['pro_name'] ?? '',
            'short_desc' => $row['short_desc'] ?? '',
            'description' => $row['description'] ?? '',
            'pro_sub_cate' => $row['pro_sub_cate'] ?? '',
            'pro_img' => $row['pro_img'] ?? 'image/product-not-found.gif',
            'slug_url' => $row['slug_url'] ?? '',
            'mrp' => $row['mrp']?? '00',
            'selling_price' => $row['selling_price']?? '00',
            'meta_title' => $row['meta_title'] ?? '',
            'meta_desc' => $row['meta_desc'] ?? '',
            'meta_key' => $row['meta_key'] ?? ''
        ];
    } else {
        // If product not found, return default values
        return [
            'pro_name' => 'No Product Available',
            'short_desc' => '',
            'description' => '',
            'pro_sub_cate' => '',
            'pro_img' => 'image/product-not-found.gif',
            'slug_url' => '',
            'meta_title' => 'Product Not Found',
            'meta_desc' => '',
            'meta_key' => ''
        ];
    }
}


// footer product 
function footer_product()
{
    global $conn;

    $sql_foot = "SELECT * FROM `products` limit 8";
    $res_foot = mysqli_query($conn, $sql_foot);

    $product = [];

    if (!$res_foot) {
        header('Location: 500.php');
    }
    while ($row = mysqli_fetch_assoc($res_foot)) {
        if (!$row) {
            header("Location: 404.php");
        } else {
            $product[] = $row;
        }
    }
    return $product;
}

function testimonial(){
    global $conn;

    $sql_test = "SELECT * FROM `testimonials` ";
    $res_test = mysqli_query($conn, $sql_test);

    $test = [];

    if(!$res_test){
        header('Location: 500.php');
    }else{
        while($row = mysqli_fetch_assoc($res_test)){
            if(!$row){
                header('Location: 404.php');
            }else{
                $test[] = $row;
            }
        }
    }
    return $test;
}

function getAwards(){
    global $conn;

    $sql_test = "SELECT * FROM `awards` ";
    $res_test = mysqli_query($conn, $sql_test);

    $test = [];

    if(!$res_test){
        header('Location: 500.php');
    }else{
        while($row = mysqli_fetch_assoc($res_test)){
            if(!$row){
                header('Location: 404.php');
            }else{
                $test[] = $row;
            }
        }
    }
    return $test;
}

function getmanagement(){
    global $conn;

    $sql_test = "SELECT * FROM `management_team` where `is_active` = 1 order by `display_order` ";
    $res_test = mysqli_query($conn, $sql_test);

    $test = [];

    if(!$res_test){
        header('Location: 500.php');
    }else{
        while($row = mysqli_fetch_assoc($res_test)){
            if(!$row){
                header('Location: 404.php');
            }else{
                $test[] = $row;
            }
        }
    }
    return $test;
}

function fetch_product_brand() {
    global $conn;

    if (!isset($_GET['alias']) || empty($_GET['alias'])) {
        return []; // Return empty array if alias not provided
    }

    // Decode the URL value before using it
    $alias = urldecode($_GET['alias']);
    $alias = mysqli_real_escape_string($conn, $alias);

    $sql = "SELECT * FROM `products` WHERE `brand_name` = '$alias' AND `status` = 1";
    $result = mysqli_query($conn, $sql);

    $products = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
    }

    return $products;
}


// ftech doctors

function getDoctors(){
    global $conn;
     $sql = "SELECT * FROM `doctors` WHERE  `status` = 'Active'";
    $result = mysqli_query($conn, $sql);

    $products = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
    }

    return $products;
}

function getDoctorsBySlug() {
    global $conn;

    if (!isset($_GET['alias']) || empty(trim($_GET['alias']))) {
        return []; // Return empty array if alias not provided
    }

    // Decode and sanitize the URL value
    $alias = urldecode($_GET['alias']);
    $alias = trim($alias);
    
    // Use prepared statement to prevent SQL injection
    $sql = "SELECT * FROM `doctors` WHERE `slug_url` = ? AND `status` = 'Active' LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        return []; // Return empty array if statement preparation fails
    }
    
    mysqli_stmt_bind_param($stmt, "s", $alias);
    mysqli_stmt_execute($stmt);
    
    $result = mysqli_stmt_get_result($stmt);
    $doctors = [];

    if ($result && mysqli_num_rows($result) > 0) {
        $doctors = mysqli_fetch_assoc($result); // Since LIMIT 1, fetch single row
    }

    mysqli_stmt_close($stmt);
    return $doctors;
}


function faq_home()
{
    global $conn;

    $sql_test = "SELECT * FROM `faqs` WHERE `page_name` = 'home' AND `status` = 1";
    $res_test = mysqli_query($conn, $sql_test);

    $test = [];

    if (!$res_test) {
        header('Location: 500.php');
    } else {
        while ($row = mysqli_fetch_assoc($res_test)) {
            if (!$row) {
                header('Location: 404.php');
            } else {
                $test[] = $row;
            }
        }
    }
    return $test;
}


// ... existing functions ...



function send_otp_email($email, $otp_code) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Or your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'nik007guptadu@gmail.com'; // Your email
        $mail->Password = 'ltmnhrwacmwmcrni'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Alternatively, for local development without SMTP:
        // $mail->isMail(); // Use PHP's mail() function
        
        // Recipients
        $mail->setFrom('no-reply@rejuvenatehealth.com', 'REJUVENATE Digital Health');
        $mail->addAddress($email); // Add a recipient
        $mail->addReplyTo('support@rejuvenatehealth.com', 'Support Team');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code - REJUVENATE Digital Health';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>OTP Verification</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c5aa0; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
                .otp-code { background: #ffffff; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; margin: 20px 0; border: 2px dashed #2c5aa0; border-radius: 5px; }
                .footer { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>REJUVENATE Digital Health</h1>
                </div>
                <div class='content'>
                    <h2>Email Verification</h2>
                    <p>Hello,</p>
                    <p>Thank you for registering with REJUVENATE Digital Health. Use the OTP code below to verify your email address:</p>
                    
                    <div class='otp-code'>$otp_code</div>
                    
                    <div class='warning'>
                        <strong>Important:</strong> This OTP will expire in 10 minutes. Do not share this code with anyone.
                    </div>
                    
                    <p>If you didn't request this verification, please ignore this email or contact our support team if you have concerns.</p>
                    
                    <p>Best regards,<br>REJUVENATE Digital Health Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " REJUVENATE Digital Health. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Alternative plain text version for non-HTML email clients
        $mail->AltBody = "REJUVENATE Digital Health - OTP Verification\n\n" .
                        "Your OTP code is: $otp_code\n\n" .
                        "This OTP will expire in 10 minutes.\n\n" .
                        "If you didn't request this, please ignore this email.\n\n" .
                        "Best regards,\nREJUVENATE Digital Health Team";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

function send_otp_sms($mobile, $otp_code) {
    // Using Twilio API
    $account_sid = 'YOUR_TWILIO_ACCOUNT_SID';
    $auth_token = 'YOUR_TWILIO_AUTH_TOKEN';
    $twilio_number = 'YOUR_TWILIO_PHONE_NUMBER';
    
    // Check if Twilio is installed
    if (class_exists('Twilio\Rest\Client')) {
        $client = new Twilio\Rest\Client($account_sid, $auth_token);
        
        try {
            $message = $client->messages->create(
                $mobile,
                [
                    'from' => $twilio_number,
                    'body' => "Your REJUVENATE Digital Health OTP code is: $otp_code. Valid for 10 minutes."
                ]
            );
            return true;
        } catch (Exception $e) {
            error_log("SMS sending failed: " . $e->getMessage());
            return false;
        }
    } else {
        error_log("Twilio SDK not installed");
        return false;
    }
}

function send_otp_sms_textlocal($mobile, $otp_code) {
    $apiKey = 'YOUR_TEXTLOCAL_API_KEY';
    $sender = 'REJUVN';
    $message = urlencode("Your REJUVENATE OTP is: $otp_code. Valid for 10 minutes.");
    
    // Prepare data for POST request
    $data = array(
        'apikey' => $apiKey,
        'numbers' => $mobile,
        'sender' => $sender,
        'message' => $message
    );
    
    // Send the POST request with cURL
    $ch = curl_init('https://api.textlocal.in/send/');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for testing, remove in production
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        error_log('cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    // Parse response
    $result = json_decode($response, true);
    
    if ($result && isset($result['status']) && $result['status'] == 'success') {
        return true;
    } else {
        error_log('TextLocal API Error: ' . $response);
        return false;
    }
}

// Enhanced function to send both email and SMS
function send_verification_otp($email, $mobile, $otp_code) {
    $results = [
        'email_sent' => false,
        'sms_sent' => false,
        'errors' => []
    ];
    
    // Send Email OTP
    $results['email_sent'] = send_otp_email($email, $otp_code);
    if (!$results['email_sent']) {
        $results['errors'][] = 'Failed to send email OTP';
    }
    
    // Send SMS OTP (uncomment when ready)
    // $results['sms_sent'] = send_otp_sms_textlocal($mobile, $otp_code);
    // if (!$results['sms_sent']) {
    //     $results['errors'][] = 'Failed to send SMS OTP';
    // }
    
    return $results;
}
?>

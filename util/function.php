<?php
include_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/../lib/DoctorAccess.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

function get_favicon()
{
    global $conn;

    $sql_logo = "SELECT * FROM `logos` where `location` = 'favicon' order by id desc limit 1";
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
    // $sql = "SELECT * FROM `sub_categories` where `parent_id` = 20873 AND `status` = 1 order by `id`"; 
    $sql = "SELECT * FROM `sub_categories` where `parent_id` = 20873 AND `status` = 1 order by `id`"; 

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

function get_sub_category_home()
{
    global $conn;
    $sub_category = [];

    // Admin explicitly curates which departments appear on the home page via
    // the `show_on_home` flag (Sub Department Management in the admin panel).
    $sql = "SELECT * FROM `sub_categories` where `parent_id` = 20873 AND `status` = 1 AND `show_on_home` = 1 order by `id` desc";

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

// Single department lookup by slug — used for the department detail page banner
function get_department_by_slug($slug)
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM `sub_categories` WHERE `slug_url` = ? AND `parent_id` = 20873 AND `status` = 1 LIMIT 1");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $department = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $department ?: null;
}

function get_doctor_byDepartment()
{
    global $conn;

    if (!isset($_GET['alias'])) {
        header("Location: index.php");
        exit();
    }

    $alias = $_GET['alias'];
    $doctors = [];

    $stmt = $conn->prepare("SELECT d.*, dd.doctor_id, dd.category_id,
            sc.categories as depart_name, sc.cate_id,
            sc.slug_url as department_slug
            FROM doctors d
            LEFT JOIN doctor_departments dd
                ON d.id = dd.doctor_id
            LEFT JOIN sub_categories sc
                ON dd.category_id = sc.cate_id
            WHERE sc.slug_url = ?
            AND d.status = 'Active'
            AND " . doctor_active_sql_condition('d') . "
            ORDER BY d.id");
    $stmt->bind_param('s', $alias);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = mysqli_fetch_assoc($result)) {
        $doctors[] = $row;
    }
    $stmt->close();

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
    // global $site;

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
        header("Location: ".BASE_URL."404.php");
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

    $sql_test = "SELECT * FROM `faqs` WHERE `page_name` = 'home' AND `status` = 1 ORDER BY `id` DESC";
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

function faq_all()
{
    global $conn;

    $sql = "SELECT * FROM `faqs` WHERE `page_name` = 'faq' AND `status` = 1 ORDER BY `id` DESC";
    $res = mysqli_query($conn, $sql);

    $faqs = [];

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $faqs[] = $row;
        }
    }
    return $faqs;
}


// ... existing functions ...



/* send_otp_email() is provided by util/mail_config.php (loaded above) */

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

/**
 * Find an existing patient account by email or mobile, or create one on
 * the spot using the info they filled in on the booking form. Called from
 * util/appointment-handler.php before insert_appointment(), so every
 * booking — even from a first-time visitor who never signed up — ends up
 * tied to a real users.id instead of leaving appointments.user_id NULL.
 *
 * @return array{user_id:int, created:bool, temp_password:?string}
 */
function find_or_create_patient_user(mysqli $conn, string $name, string $email, string $mobile): array
{
    $mobileClean = preg_replace('/\D/', '', $mobile);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR mobile = ? LIMIT 1");
    $stmt->bind_param('ss', $email, $mobileClean);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        return ['user_id' => (int) $existing['id'], 'created' => false, 'temp_password' => null];
    }

    // New account — generate a temp password so they can actually log in
    // later (process-login.php always requires a password; there's no
    // password-less login for patients).
    $tempPassword = bin2hex(random_bytes(5)); // 10 hex chars
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    $ins = $conn->prepare("INSERT INTO users (name, email, mobile, password, status, created_at) VALUES (?, ?, ?, ?, 'Active', NOW())");
    $ins->bind_param('ssss', $name, $email, $mobileClean, $hash);
    $ins->execute();

    return ['user_id' => (int) $conn->insert_id, 'created' => true, 'temp_password' => $tempPassword];
}

/**
 * Insert the appointment and (best-effort) email three notifications, in
 * order: admin, then patient, then doctor (if a specific doctor was
 * matched). All via the shared Mailer (util/mail_config.php).
 *
 * For online consultations, the video call room + join link are created
 * up front (see telemedicine/helpers.php) so the link can go out in the
 * patient/doctor emails immediately — it only "activates" once the doctor
 * approves the appointment (see telemedicine/join.php's status check).
 *
 * Returns the new appointment's insert ID on success, or false if the
 * booking itself could not be saved. A failed notification email does
 * NOT cause this to return false — the DB insert already succeeded,
 * that's what actually matters to the patient.
 */
function send_appointment_email( $data) {
    global $conn;

    // 1️⃣ Insert appointment into DB
    $appointmentId = insert_appointment( $data);
    if (!$appointmentId) {
        return false;
    }

    // Re-fetch the saved row so the emails below work off validated DB
    // state (confirmed doctor_id, etc.) rather than raw client input.
    $stmt = $conn->prepare("SELECT a.*, d.name AS doctor_name, d.email AS doctor_email
        FROM appointments a
        LEFT JOIN doctors d ON d.id = a.doctor_id
        WHERE a.id = ? LIMIT 1");
    $stmt->bind_param('i', $appointmentId);
    $stmt->execute();
    $appt = $stmt->get_result()->fetch_assoc();

    $typeLabel = ($appt['appointment_type'] ?? 'online') === 'clinic' ? 'In-Clinic Visit' : 'Online Consultation';
    $dateLabel = !empty($appt['appointment_date']) ? date('d M Y', strtotime($appt['appointment_date'])) : $data['date'];
    $timeLabel = !empty($appt['appointment_time']) ? date('h:i A', strtotime($appt['appointment_time'])) : $data['time'];

    // Create the telemedicine room up front for online consultations, so
    // the join link can be included in the emails below.
    $meetingLink = null;        // goes to the doctor (always has an account, logs in via JWT)
    $patientMeetingLink = null; // goes to the patient — may need a guest link, see below
    if ($appt && $appt['appointment_type'] === 'online') {
        require_once __DIR__ . '/../telemedicine/helpers.php';
        $room = telemedicine_ensure_room($conn, $appointmentId);
        $meetingLink = $room['link'] ?? null;
        $patientMeetingLink = $meetingLink;

        // A guest booking (no account -> appointments.user_id is NULL) can't
        // prove ownership via a login session, so the plain link would just
        // bounce them to the login page forever. Mail them a signed,
        // appointment-scoped link instead — see telemedicine_guest_link().
        if ($meetingLink && empty($appt['user_id'])) {
            $patientMeetingLink = telemedicine_guest_link($appointmentId);
        }
    }

    // 2️⃣ Admin notification — first
    $contact = contact_us();
    $adminEmail = $contact['email'] ?? MAIL_USERNAME;

    $bodyHtml = "
        <p>A new appointment request has been received.</p>
        <table style='width:100%;border-collapse:collapse;font-size:14px;'>
            <tr><td style='padding:8px 0;font-weight:bold;width:40%;'>Name</td><td>" . htmlspecialchars($data['name']) . "</td></tr>
            <tr><td style='padding:8px 0;font-weight:bold;'>Email</td><td>" . htmlspecialchars($data['email']) . "</td></tr>
            <tr><td style='padding:8px 0;font-weight:bold;'>Phone</td><td>" . htmlspecialchars($data['phone']) . "</td></tr>
            <tr><td style='padding:8px 0;font-weight:bold;'>Department</td><td>" . htmlspecialchars($data['department']) . "</td></tr>" .
            (!empty($data['doctor_name']) ? "<tr><td style='padding:8px 0;font-weight:bold;'>Preferred Doctor</td><td>" . htmlspecialchars($data['doctor_name']) . "</td></tr>" : "") . "
            <tr><td style='padding:8px 0;font-weight:bold;'>Date</td><td>" . htmlspecialchars($dateLabel) . "</td></tr>
            <tr><td style='padding:8px 0;font-weight:bold;'>Time</td><td>" . htmlspecialchars($timeLabel) . "</td></tr>
            <tr><td style='padding:8px 0;font-weight:bold;'>Type</td><td>" . htmlspecialchars($typeLabel) . "</td></tr>
        </table>
    ";

    $bodyText =
        "New Appointment Booking\n\n" .
        "Name: {$data['name']}\n" .
        "Email: {$data['email']}\n" .
        "Phone: {$data['phone']}\n" .
        "Department: {$data['department']}\n" .
        (!empty($data['doctor_name']) ? "Preferred Doctor: {$data['doctor_name']}\n" : "") .
        "Date: {$dateLabel}\n" .
        "Time: {$timeLabel}\n" .
        "Type: {$typeLabel}\n";

    (new Mailer())->sendCustom($adminEmail, 'Admin', 'New Appointment Booking Request', $bodyHtml, $bodyText);

    if ($appt) {
        // 3️⃣ Patient notification — second. If find_or_create_patient_user()
        // just created a fresh account for them, fold the temp login
        // credentials into this same email rather than sending a separate
        // "welcome" email (keeps the promised 3-emails-per-booking flow intact).
        (new Mailer())->sendAppointmentRequested(
            $appt['patient_email'],
            $appt['patient_name'],
            [
                'doctor_name'    => $appt['doctor_name'] ?? ($data['doctor_name'] ?? ''),
                'date'           => $dateLabel,
                'time'           => $timeLabel,
                'type'           => $typeLabel,
                'meeting_link'   => $patientMeetingLink,
                'account_created'=> !empty($data['_new_account_temp_password']),
                'login_email'    => $appt['patient_email'],
                'temp_password'  => $data['_new_account_temp_password'] ?? null,
            ]
        );

        // 4️⃣ Doctor notification — third, only when a specific doctor was matched
        if (!empty($appt['doctor_id']) && !empty($appt['doctor_email'])) {
            (new Mailer())->sendNewAppointmentDoctor(
                $appt['doctor_email'],
                $appt['doctor_name'],
                [
                    'patient_name' => $appt['patient_name'],
                    'date'         => $dateLabel,
                    'time'         => $timeLabel,
                    'type'         => $typeLabel,
                    'purpose'      => $appt['purpose'],
                    'meeting_link' => $meetingLink,
                ]
            );
        }
    }

    return $appointmentId;
}

/**
 * Insert a new appointment request.
 * Returns the new appointment's insert ID on success, or false on failure.
 *
 * Required $data keys: name, email, phone, department, date, time
 * Optional: doctor_id, doctor_name, abha_number, user_id, notes,
 *           appointment_type ('online'|'clinic', default 'online'),
 *           visit_person ('self'|'other', default 'self'), visited_person_name,
 *           consent_given (bool), payment_status, payment_amount,
 *           razorpay_order_id, razorpay_payment_id, razorpay_signature
 *           (payment_* set by appointment-handler.php after verifying Razorpay)
 */
function insert_appointment($data) {
    global $conn;

    // doctor_id is optional — set when the request came from a specific doctor's
    // "Book an Appointment" button so the request is actually tied to that doctor,
    // not just a free-text department name.
    $doctorId = !empty($data['doctor_id']) ? (int) $data['doctor_id'] : null;

    if ($doctorId) {
        // Confirm it's a real, active doctor before trusting client input
        $chk = mysqli_prepare($conn, "SELECT id FROM doctors WHERE id = ? AND status = 'Active' LIMIT 1");
        mysqli_stmt_bind_param($chk, "i", $doctorId);
        mysqli_stmt_execute($chk);
        if (!mysqli_stmt_get_result($chk)->fetch_assoc()) {
            $doctorId = null;
        }
        mysqli_stmt_close($chk);
    }

    $userId = !empty($data['user_id']) ? (int) $data['user_id'] : null;

    $visitPerson = ($data['visit_person'] ?? 'self') === 'other' ? 'other' : 'self';
    $visitedPersonName = $visitPerson === 'other' ? trim($data['visited_person_name'] ?? '') : null;

    $consentGiven = !empty($data['consent_given']) ? 1 : 0;
    $consentAt    = $consentGiven ? date('Y-m-d H:i:s') : null;

    // Payment — set by appointment-handler.php after verifying the Razorpay
    // signature (or 'not_required' when the doctor has no consultation_fee).
    $paymentStatus  = $data['payment_status'] ?? 'not_required';
    $paymentAmount  = isset($data['payment_amount']) ? (float) $data['payment_amount'] : null;
    $razorpayOrder  = $data['razorpay_order_id'] ?? null;
    $razorpayPayment = $data['razorpay_payment_id'] ?? null;
    $razorpaySignature = $data['razorpay_signature'] ?? null;
    $paidAt = $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null;

    $sql = "INSERT INTO appointments (
                patient_name,
                patient_email,
                patient_phone,
                abha_number,
                user_id,
                doctor_id,
                appointment_date,
                appointment_time,
                purpose,
                notes,
                appointment_type,
                visit_person,
                visited_person_name,
                consent_given,
                consent_at,
                status,
                payment_status,
                payment_amount,
                razorpay_order_id,
                razorpay_payment_id,
                razorpay_signature,
                paid_at,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return false;
    }

    // ✅ DEFINE VARIABLES FIRST
    $name            = $data['name'];
    $email           = $data['email'];
    $phone           = $data['phone'];
    $abhaNumber      = !empty($data['abha_number']) ? $data['abha_number'] : null;
    $date            = $data['date'];
    $time            = $data['time'];
    $department      = $data['department'];
    $notes           = trim($data['notes'] ?? '');
    $appointmentType = ($data['appointment_type'] ?? 'online') === 'clinic' ? 'clinic' : 'online';

    // ✅ THEN BIND
    mysqli_stmt_bind_param(
        $stmt,
        "ssssiisssssssissdssss",
        $name,
        $email,
        $phone,
        $abhaNumber,
        $userId,
        $doctorId,
        $date,
        $time,
        $department,
        $notes,
        $appointmentType,
        $visitPerson,
        $visitedPersonName,
        $consentGiven,
        $consentAt,
        $paymentStatus,
        $paymentAmount,
        $razorpayOrder,
        $razorpayPayment,
        $razorpaySignature,
        $paidAt
    );

    // ✅ EXECUTE
    $result = mysqli_stmt_execute($stmt);
    $insertId = $result ? mysqli_insert_id($conn) : false;

    if (!$result) {
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    return $insertId;
}

/**
 * Insert a contact-us inquiry.
 * Required $data keys: name, email, subject, message. Optional: phone.
 * Returns the new inquiry's insert ID on success, or false on failure.
 */
function insert_inquiry($data) {
    global $conn;

    $stmt = mysqli_prepare($conn, "
        INSERT INTO inquiries (name, email, phone, subject, message)
        VALUES (?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $data['name'],
        $data['email'],
        $data['phone'],
        $data['subject'],
        $data['message']
    );

    $result = mysqli_stmt_execute($stmt);
    $insertId = $result ? mysqli_insert_id($conn) : false;

    if (!$result) {
        error_log("Inquiry insert failed: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    return $insertId;
}

/**
 * Insert the inquiry and (best-effort) email a notification to the site's
 * configured contact address. Returns the new inquiry's insert ID on success,
 * or false if the inquiry itself could not be saved. A failed notification
 * email does NOT cause this to return false — same pattern as
 * send_appointment_email() above.
 */
function send_inquiry_email($data, $toEmail) {
    $inquiryId = insert_inquiry($data);
    if (!$inquiryId) {
        return false;
    }

    if (empty($toEmail)) {
        return $inquiryId;
    }

    // Best-effort admin notification via the shared Mailer (util/mail_config.php,
    // credentials from .env) — a failed email must not make the visitor think
    // their inquiry wasn't received; the DB insert above already succeeded.
    $bodyHtml = "
        <p>A new contact us inquiry has been received.</p>
        <table style='width:100%;border-collapse:collapse;font-size:14px;'>
            <tr><td style='padding:8px 0;font-weight:bold;width:30%;'>Name</td><td>" . htmlspecialchars($data['name']) . "</td></tr>
            <tr><td style='padding:8px 0;font-weight:bold;'>Email</td><td>" . htmlspecialchars($data['email']) . "</td></tr>" .
            (!empty($data['phone']) ? "<tr><td style='padding:8px 0;font-weight:bold;'>Phone</td><td>" . htmlspecialchars($data['phone']) . "</td></tr>" : "") . "
            <tr><td style='padding:8px 0;font-weight:bold;'>Subject</td><td>" . htmlspecialchars($data['subject']) . "</td></tr>
            <tr><td style='padding:8px 0;font-weight:bold;'>Message</td><td>" . nl2br(htmlspecialchars($data['message'])) . "</td></tr>
        </table>
    ";

    $bodyText =
        "New Contact Us Inquiry\n\n" .
        "Name: {$data['name']}\n" .
        "Email: {$data['email']}\n" .
        (!empty($data['phone']) ? "Phone: {$data['phone']}\n" : "") .
        "Subject: {$data['subject']}\n" .
        "Message: {$data['message']}\n";

    (new Mailer())->sendCustom($toEmail, 'Admin', 'New Contact Us Inquiry: ' . $data['subject'], $bodyHtml, $bodyText);

    return $inquiryId;
}

/**
 * Active doctors practising in a given department (by department slug).
 * Used by the public "Book an Appointment" page's department -> doctor step.
 */
function get_doctors_by_department_slug($slug) {
    global $conn;
    $doctors = [];

    $stmt = mysqli_prepare($conn, "
        SELECT DISTINCT d.id, d.name, d.slug_url, d.degrees, d.specialization,
               d.experience_years, d.rating, d.languages, d.profile_image,
               d.consultation_fee, d.hpr_verified
        FROM doctors d
        JOIN doctor_departments dd ON dd.doctor_id = d.id
        JOIN sub_categories sc ON sc.cate_id = dd.category_id
        WHERE sc.slug_url = ? AND sc.parent_id = 20873 AND d.status = 'Active'
          AND " . doctor_active_sql_condition('d') . "
        ORDER BY d.name ASC
    ");
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return $doctors;
    }
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $doctors[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $doctors;
}

/**
 * Available appointment slots for a doctor on a given date.
 * Standard clinic hours 9:00–17:30 in 30-minute steps with a 12:00–14:00
 * lunch break, minus whatever is already booked (pending/approved) for
 * that doctor on that date. Past times on today's date are excluded.
 *
 * Returns [ ['time' => '09:00:00', 'display' => '09:00 AM', 'booked' => bool], ... ]
 */
function get_available_slots($doctorId, $date) {
    global $conn;

    $doctorId = (int) $doctorId;
    $slots = [];

    $timeSlots = [
        '09:00:00' => '09:00 AM', '09:30:00' => '09:30 AM',
        '10:00:00' => '10:00 AM', '10:30:00' => '10:30 AM',
        '11:00:00' => '11:00 AM', '11:30:00' => '11:30 AM',
        '12:00:00' => '12:00 PM',
        '14:00:00' => '02:00 PM', '14:30:00' => '02:30 PM',
        '15:00:00' => '03:00 PM', '15:30:00' => '03:30 PM',
        '16:00:00' => '04:00 PM', '16:30:00' => '04:30 PM',
        '17:00:00' => '05:00 PM', '17:30:00' => '05:30 PM',
    ];

    $bookedSlots = [];
    $stmt = mysqli_prepare($conn, "
        SELECT appointment_time FROM appointments
        WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending', 'approved')
    ");
    mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $bookedSlots[] = $row['appointment_time'];
    }
    mysqli_stmt_close($stmt);

    $isToday = $date === date('Y-m-d');
    $now = date('H:i:s');

    foreach ($timeSlots as $time => $display) {
        if ($isToday && $time <= $now) continue; // don't offer times already past today
        $slots[] = [
            'time'   => $time,
            'display' => $display,
            'booked' => in_array($time, $bookedSlots, true),
        ];
    }

    return $slots;
}

?>

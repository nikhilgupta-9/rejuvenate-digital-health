<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (SESSION_STATUS() == PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . "/../config/connect.php");

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";
$error_message = "";

// Handle form submission
if (isset($_POST['profile_update'])) {

    // Sanitize and validate input data
    $name       = trim($_POST['name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $mobile     = trim($_POST['mobile'] ?? '');
    $gender     = $_POST['gender'] ?? '';
    $dob        = $_POST['dob'] ?? '';
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $state      = trim($_POST['state'] ?? '');
    $zip_code   = trim($_POST['zip_code'] ?? '');

    // VALIDATION ----------
    if (empty($name)) {
        $errors['name'] = "First name is required";
    }

    if (empty($mobile)) {
        $errors['mobile'] = "Mobile number is required";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors['mobile'] = "Please enter a valid 10-digit mobile number";
    } else {
        // Check if mobile exists for another user
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ? AND id != ?");
        $check_stmt->bind_param("si", $mobile, $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $errors['mobile'] = "Mobile number already registered with another account";
        }
        $check_stmt->close();
    }

    if (!empty($dob) && strtotime($dob) > time()) {
        $errors['dob'] = "Date of birth cannot be in the future";
    }

    // IF NO ERRORS → UPDATE
    if (empty($errors)) {

        $update_stmt = $conn->prepare("
            UPDATE users 
            SET 
                name = ?, 
                last_name = ?, 
                mobile = ?, 
                gender = ?, 
                dob = ?, 
                address = ?, 
                city = ?, 
                state = ?, 
                zip_code = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");

        $update_stmt->bind_param(
            "sssssssssi",
            $name,
            $last_name,
            $mobile,
            $gender,
            $dob,
            $address,
            $city,
            $state,
            $zip_code,
            $user_id
        );

        if ($update_stmt->execute()) {

            $success_message = "Profile updated successfully!";

            // Update session small info (not whole profile)
            $_SESSION['user_name'] = $name;

            // Reload updated user data
            $refresh_stmt = $conn->prepare("
                SELECT name, last_name, email, mobile, gender, dob, address, city, state, zip_code
                FROM users
                WHERE id = ?
            ");

            $refresh_stmt->bind_param("i", $user_id);
            $refresh_stmt->execute();
            $result = $refresh_stmt->get_result();
            $user_data = $result->fetch_assoc();
            $refresh_stmt->close();

            // Recalculate age
            if (!empty($user_data['dob']) && $user_data['dob'] != '0000-00-00') {
                $dobObj = new DateTime($user_data['dob']);
                $today = new DateTime();
                $age = $today->diff($dobObj)->y;
            }
            header("Location: " . $site . "user/my-profile.php?updated=1");
            exit;
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }

        $update_stmt->close();
    } else {
        $error_message = "Please correct the errors below.";
    }
}



function update_profile_picture($user_id, $file)
{
    global $conn, $site;

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    $upload_dir = __DIR__ . '/../assets/img/profile_pics/';

    // Create if missing
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $errors = [];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error: " . $file['error'];
        return ['success' => false, 'errors' => $errors];
    }

    // Check file type
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
    }

    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = "File size must be less than 2MB.";
    }

    // If there are errors, return them
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Get old profile picture to delete it later
        $old_pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $old_pic_stmt->bind_param("i", $user_id);
        $old_pic_stmt->execute();
        $old_pic_result = $old_pic_stmt->get_result();
        $old_data = $old_pic_result->fetch_assoc();
        $old_pic_stmt->close();

        // Update database
        $update_stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
        $db_filename = 'profile_pics/' . $filename;
        $update_stmt->bind_param("si", $db_filename, $user_id);

        if ($update_stmt->execute()) {
            $update_stmt->close();

            // Delete old profile picture if it exists and is not the default
            if (!empty($old_data['profile_pic']) && $old_data['profile_pic'] !== 'dummy.png') {
                $old_file_path = $_SERVER['DOCUMENT_ROOT'] . $site . 'assets/img/' . $old_data['profile_pic'];
                if (file_exists($old_file_path) && is_file($old_file_path)) {
                    unlink($old_file_path);
                }
            }

            return [
                'success' => true,
                'filename' => $db_filename,
                'message' => 'Profile picture updated successfully!'
            ];
        } else {
            $update_stmt->close();
            // Delete the uploaded file if database update failed
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            return ['success' => false, 'errors' => ['Failed to update database.']];
        }
    } else {
        return ['success' => false, 'errors' => ['Failed to upload file.']];
    }
}

function get_user_profile_pic($user_id)
{
    global $conn, $site;

    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    if (!empty($user_data['profile_pic'])) {
        return $site . 'assets/img/' . $user_data['profile_pic'];
    } else {
        return $site . 'assets/img/dummy.png';
    }
}

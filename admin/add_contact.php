<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "db-conn.php";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize form values
    $address        = mysqli_real_escape_string($conn, $_POST['address']);
    $address2       = mysqli_real_escape_string($conn, $_POST['address2']);
    $phone          = mysqli_real_escape_string($conn, $_POST['phone']);
    $wp_number      = mysqli_real_escape_string($conn, $_POST['wp_number']);
    $telephone      = mysqli_real_escape_string($conn, $_POST['telephone']);
    $email          = mysqli_real_escape_string($conn, $_POST['email']);
    $working_hours  = mysqli_real_escape_string($conn, $_POST['working_hours'] ?? null);
    $facebook       = mysqli_real_escape_string($conn, $_POST['facebook'] ?? null);
    $instagram      = mysqli_real_escape_string($conn, $_POST['instagram'] ?? null);
    $twitter        = mysqli_real_escape_string($conn, $_POST['twitter'] ?? null);
    $linkdin        = mysqli_real_escape_string($conn, $_POST['linkdin'] ?? null);
    $map            = mysqli_real_escape_string($conn, $_POST['map'] ?? null);
    $contact_email  = mysqli_real_escape_string($conn, $_POST['contact_email'] ?? null);

    // Fetch the existing contact record
    $check = $conn->query("SELECT id FROM contacts LIMIT 1");

    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $contact_id = $row['id'];

        // Update query
        $stmt = $conn->prepare("UPDATE contacts 
            SET address = ?, 
                address2 = ?,
                phone = ?, 
                wp_number = ?,
                telephone = ?,
                email = ?, 
                working_hours = ?, 
                facebook = ?, 
                instagram = ?, 
                twitter = ?, 
                linkdin = ?,
                map = ?, 
                contact_email = ?,
                updated_at = NOW()
            WHERE id = ?");

        $stmt->bind_param(
            'sssssssssssssi', 
            $address, 
            $address2,
            $phone, 
            $wp_number,
            $telephone,
            $email, 
            $working_hours, 
            $facebook, 
            $instagram, 
            $twitter, 
            $linkdin,
            $map, 
            $contact_email,
            $contact_id
        );
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO contacts 
            (address, address2, phone, wp_number, telephone, email, working_hours, facebook, instagram, twitter, linkdin, map, contact_email) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            'sssssssssssss', 
            $address, 
            $address2,
            $phone, 
            $wp_number,
            $telephone,
            $email, 
            $working_hours, 
            $facebook, 
            $instagram, 
            $twitter, 
            $linkdin,
            $map, 
            $contact_email
        );
    }

    // Execute the statement
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Contact information updated successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating information: " . $stmt->error;
    }
}

// Fetch existing data
$result = $conn->query("SELECT * FROM contacts LIMIT 1");
$data = $result ? $result->fetch_assoc() : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Contact Information | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include "links.php"; ?>
    
    <style>
        .contact-form-container {
            background: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .section-title {
            font-weight: 600;
            color: #4e73df;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        textarea.form-control {
            min-height: 100px;
        }
        .btn-submit {
            background-color: #4e73df;
            border: none;
            padding: 12px 25px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            background-color: #3a5bc7;
            transform: translateY(-2px);
        }
        .social-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .input-with-icon {
            position: relative;
        }
        .input-with-icon input {
            padding-left: 40px;
        }
        .preview-map {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            background: #f9f9f9;
        }
        .preview-map iframe {
            width: 100%;
            height: 300px;
            border: none;
            border-radius: 5px;
        }
        .info-text {
            font-size: 13px;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>

<body class="crm_body_bg">

    <?php include "header.php"; ?>
    
    <section class="main_content dashboard_part large_header_bg">
        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Contact Information Management</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <?php if(isset($_SESSION['success_message'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(isset($_SESSION['error_message'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="contact-form-container">
                                    <form action="" method="post">
                                        <h4 class="section-title"><i class="fas fa-map-marker-alt me-2"></i> Address Information</h4>
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3">
                                                <label for="address" class="form-label">Primary Address</label>
                                                <textarea name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($data['address'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="address2" class="form-label">Secondary Address (Optional)</label>
                                                <textarea name="address2" class="form-control" rows="3"><?php echo htmlspecialchars($data['address2'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <h4 class="section-title"><i class="fas fa-phone me-2"></i> Contact Numbers</h4>
                                        <div class="row mb-4">
                                            <div class="col-md-4 mb-3">
                                                <label for="phone" class="form-label">Primary Phone</label>
                                                <div class="input-with-icon">
                                                    <i class="fas fa-mobile-alt social-icon text-primary"></i>
                                                    <input type="text" class="form-control" name="phone" 
                                                           value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="wp_number" class="form-label">WhatsApp Number</label>
                                                <div class="input-with-icon">
                                                    <i class="fab fa-whatsapp social-icon text-success"></i>
                                                    <input type="text" class="form-control" name="wp_number" 
                                                           value="<?php echo htmlspecialchars($data['wp_number'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="telephone" class="form-label">Telephone Number</label>
                                                <div class="input-with-icon">
                                                    <i class="fas fa-phone social-icon text-danger"></i>
                                                    <input type="text" class="form-control" name="telephone" 
                                                           value="<?php echo htmlspecialchars($data['telephone'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <h4 class="section-title"><i class="fas fa-envelope me-2"></i> Email Information</h4>
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Public Email Address</label>
                                                <div class="input-with-icon">
                                                    <i class="fas fa-at social-icon text-success"></i>
                                                    <input type="email" class="form-control" name="email"
                                                           value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="contact_email" class="form-label">Contact Form Receiver Email</label>
                                                <div class="input-with-icon">
                                                    <i class="fas fa-mail-bulk social-icon text-success"></i>
                                                    <input type="email" class="form-control" name="contact_email"
                                                           value="<?php echo htmlspecialchars($data['contact_email'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <h4 class="section-title"><i class="fas fa-clock me-2"></i> Business Hours</h4>
                                        <div class="row mb-4">
                                            <div class="col-12">
                                                <label for="working_hours" class="form-label">Working Hours</label>
                                                <input type="text" class="form-control" name="working_hours"
                                                       placeholder="e.g., Monday-Friday: 9:00 AM - 6:00 PM"
                                                       value="<?php echo htmlspecialchars($data['working_hours'] ?? ''); ?>">
                                                <p class="info-text">Enter your business hours in a clear format</p>
                                            </div>
                                        </div>
                                        
                                        <h4 class="section-title"><i class="fas fa-share-alt me-2"></i> Social Media Links</h4>
                                        <div class="row mb-4">
                                            <div class="col-md-4 mb-3">
                                                <label for="facebook" class="form-label">Facebook</label>
                                                <div class="input-with-icon">
                                                    <i class="fab fa-facebook-f social-icon text-primary"></i>
                                                    <input type="url" class="form-control" name="facebook"
                                                           placeholder="https://facebook.com/yourpage"
                                                           value="<?php echo htmlspecialchars($data['facebook'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="instagram" class="form-label">Instagram</label>
                                                <div class="input-with-icon">
                                                    <i class="fab fa-instagram social-icon text-danger"></i>
                                                    <input type="url" class="form-control" name="instagram"
                                                           placeholder="https://instagram.com/yourpage"
                                                           value="<?php echo htmlspecialchars($data['instagram'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="twitter" class="form-label">Twitter</label>
                                                <div class="input-with-icon">
                                                    <i class="fab fa-twitter social-icon text-info"></i>
                                                    <input type="url" class="form-control" name="twitter"
                                                           placeholder="https://twitter.com/yourpage"
                                                           value="<?php echo htmlspecialchars($data['twitter'] ?? ''); ?>">
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="twitter" class="form-label">Linkdin</label>
                                                <div class="input-with-icon">
                                                <i class="fab fa-linkedin social-icon text-primary"></i>
                                                    <input type="url" class="form-control" name="linkdin"
                                                           placeholder="https://linkdin.com/yourpage"
                                                           value="<?php echo htmlspecialchars($data['linkdin'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <h4 class="section-title"><i class="fas fa-map-marked-alt me-2"></i> Location Map</h4>
                                        <div class="row mb-4">
                                            <div class="col-12">
                                                <label for="map" class="form-label">Google Map Embed Code</label>
                                                <textarea name="map" class="form-control" rows="4" 
                                                          placeholder="Paste the full iframe code from Google Maps"><?php echo htmlspecialchars($data['map'] ?? ''); ?></textarea>
                                                <p class="info-text">Get the embed code from Google Maps > Share > Embed a map</p>
                                                
                                                <?php if(!empty($data['map'])): ?>
                                                <div class="preview-map">
                                                    <h6><i class="fas fa-eye me-2"></i>Map Preview</h6>
                                                    <?php echo htmlspecialchars_decode($data['map']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end mt-4">
                                            <button type="submit" class="btn btn-primary btn-submit">
                                                <i class="fas fa-save me-2"></i> Update Contact Information
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add any necessary JavaScript here
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-close alerts after 5 seconds
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    new bootstrap.Alert(alert).close();
                });
            }, 5000);
        });
    </script>
</body>
</html>
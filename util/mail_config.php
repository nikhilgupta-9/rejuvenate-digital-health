<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adjust the path based on your PHPMailer installation
require_once __DIR__ . '/../vendor/autoload.php'; // If using Composer
// OR if manual installation:
// require_once __DIR__ . '/../phpmailer/src/Exception.php';
// require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
// require_once __DIR__ . '/../phpmailer/src/SMTP.php';

class Mailer
{
    private $mail;
    private $site; // Add site variable

    public function __construct($site_url = '')
    {
        $this->site = $site_url;
        $this->mail = new PHPMailer(true);

        try {
            // SMTP Configuration - Update these with your actual SMTP settings
            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com'; // Your SMTP server
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = 'noreply@rejuvenate.com'; // Your email
            $this->mail->Password   = 'your_app_password'; // Use app password for Gmail
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Or ENCRYPTION_SMTPS
            $this->mail->Port       = 587; // Or 465 for SSL

            // Set from address
            $this->mail->setFrom('noreply@rejuvenate.com', 'REJUVENATE Digital Health');
            $this->mail->isHTML(true);

            // Optional: Set SMTP debug mode (0 = off, 1 = messages, 2 = messages and connections)
            $this->mail->SMTPDebug = 0;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $e->getMessage());
        }
    }

    // Send verification email to doctor
    public function sendVerificationEmail($doctorEmail, $doctorName, $verifiedBy = 'Administrator')
    {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();

            // Add recipient
            $this->mail->addAddress($doctorEmail, "Dr. " . $doctorName);

            // Email subject
            $this->mail->Subject = 'Account Verified - REJUVENATE Digital Health';

            // Get email template
            $this->mail->Body = $this->getVerificationEmailTemplate($doctorName, $verifiedBy);
            $this->mail->AltBody = $this->getPlainTextTemplate($doctorName, $verifiedBy);

            // Send email
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Mailer Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    // HTML email template
    private function getVerificationEmailTemplate($name, $verifiedBy)
    {
        $loginUrl = $this->site . "doctor-login.php";
        $currentDate = date('F j, Y');

        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Account Verified</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0;
                    padding: 0;
                    background-color: #f5f5f5;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 28px; 
                }
                .header p {
                    margin: 10px 0 0;
                    opacity: 0.9;
                }
                .content { 
                    padding: 40px 30px; 
                }
                .btn { 
                    display: inline-block; 
                    background: #667eea; 
                    color: white; 
                    padding: 14px 32px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    font-weight: bold; 
                    font-size: 16px;
                    margin: 20px 0;
                    border: none;
                    cursor: pointer;
                }
                .footer { 
                    margin-top: 40px; 
                    padding-top: 20px; 
                    border-top: 1px solid #eee; 
                    font-size: 12px; 
                    color: #666; 
                    text-align: center;
                }
                .details { 
                    background: #f9f9f9; 
                    border: 1px solid #eee; 
                    border-radius: 8px; 
                    padding: 25px; 
                    margin: 25px 0; 
                }
                .detail-item { 
                    margin: 12px 0; 
                    display: flex;
                    align-items: center;
                }
                .detail-item i {
                    margin-right: 10px;
                    color: #667eea;
                    width: 20px;
                    text-align: center;
                }
                .features {
                    margin: 30px 0;
                }
                .feature-item {
                    display: flex;
                    align-items: center;
                    margin: 10px 0;
                    padding: 10px;
                    background: #f8f9ff;
                    border-radius: 6px;
                }
                .feature-item i {
                    color: #28a745;
                    margin-right: 10px;
                }
                .text-center {
                    text-align: center;
                }
                .mt-3 { margin-top: 15px; }
                .mt-4 { margin-top: 20px; }
                .mb-3 { margin-bottom: 15px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎉 Account Verified Successfully!</h1>
                    <p>Welcome to REJUVENATE Digital Health Platform</p>
                </div>
                
                <div class="content">
                    <h2 style="color: #333; margin-bottom: 20px;">Dear Dr. ' . htmlspecialchars($name) . ',</h2>
                    
                    <p style="font-size: 16px; line-height: 1.6;">We are pleased to inform you that your doctor account has been <strong>successfully verified and approved</strong> by our administration team.</p>
                    
                    <div class="details">
                        <h3 style="color: #444; margin-top: 0;">📋 Account Details:</h3>
                        <div class="detail-item">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Status:</strong> 
                                <span style="color: #28a745; font-weight: bold;">✅ Verified & Active</span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar-alt"></i>
                            <div><strong>Verification Date:</strong> ' . $currentDate . '</div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-user-check"></i>
                            <div><strong>Verified By:</strong> ' . htmlspecialchars($verifiedBy) . '</div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-arrow-right"></i>
                            <div><strong>Next Step:</strong> Login to access your dashboard</div>
                        </div>
                    </div>
                    
                    <p style="font-size: 16px;">You can now access all features of the doctor panel:</p>
                    
                    <div class="features">
                        <div class="feature-item">
                            <i class="fas fa-user-md"></i>
                            <span>Manage your professional profile and availability</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-calendar-check"></i>
                            <span>View and accept patient appointments</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-file-medical"></i>
                            <span>Access patient medical records securely</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-prescription"></i>
                            <span>Generate digital prescriptions and reports</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-chart-line"></i>
                            <span>Track consultations and professional analytics</span>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <a href="' . $loginUrl . '" class="btn" style="text-decoration: none; color: white;">
                            🚀 Login to Your Dashboard
                        </a>
                    </div>
                    
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 25px 0; border-radius: 4px;">
                        <strong>📝 Important Notes:</strong>
                        <ul style="margin: 10px 0 0; padding-left: 20px;">
                            <li>Keep your login credentials secure and confidential</li>
                            <li>Complete your profile details for better patient visibility</li>
                            <li>Set your consultation hours and fees</li>
                            <li>Upload required documents for additional verification</li>
                        </ul>
                    </div>
                    
                    <p style="color: #666;">If you need any assistance or have questions, please don\'t hesitate to contact our support team.</p>
                    
                    <p style="font-size: 14px; color: #888;">
                        <em>This is an automated notification. Please do not reply to this email.</em>
                    </p>
                </div>
                
                <div class="footer">
                    <p>© ' . date('Y') . ' REJUVENATE Digital Health. All rights reserved.</p>
                    <p>This email was sent to ' . htmlspecialchars($doctorEmail) . ' as part of your account registration.</p>
                    <p style="font-size: 11px;">
                        <a href="' . $this->site . 'privacy-policy" style="color: #667eea;">Privacy Policy</a> | 
                        <a href="' . $this->site . 'terms" style="color: #667eea;">Terms of Service</a> | 
                        <a href="' . $this->site . 'contact" style="color: #667eea;">Contact Support</a>
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }

    // Plain text version for email clients that don't support HTML
    private function getPlainTextTemplate($name, $verifiedBy)
    {
        $loginUrl = $this->site . "doctor-login.php";
        $currentDate = date('F j, Y');

        return "ACCOUNT VERIFIED - REJUVENATE Digital Health

Dear Dr. $name,

We are pleased to inform you that your doctor account has been successfully verified and approved by our administration team.

Account Details:
- Status: Verified & Active
- Verification Date: $currentDate
- Verified By: $verifiedBy
- Next Step: Login to access your dashboard

You can now access all features of the doctor panel:
1. Manage your professional profile and availability
2. View and accept patient appointments
3. Access patient medical records securely
4. Generate digital prescriptions and reports
5. Track consultations and professional analytics

Login to your dashboard: $loginUrl

Important Notes:
- Keep your login credentials secure and confidential
- Complete your profile details for better patient visibility
- Set your consultation hours and fees
- Upload required documents for additional verification

If you need any assistance or have questions, please contact our support team.

Best regards,
REJUVENATE Digital Health Team

© " . date('Y') . " REJUVENATE Digital Health. All rights reserved.
This email was sent as part of your account registration.";
    }
}

// Function to send verification email (standalone function for easy use)
function sendDoctorVerificationEmail($conn, $doctorId, $adminId, $siteUrl)
{
    try {
        // First, get doctor details and admin name
        $doctorQuery = "SELECT d.email, d.name, a.username as admin_name 
                       FROM doctors d 
                       LEFT JOIN admin_user a ON ? = a.id 
                       WHERE d.id = ?";
        $stmt = $conn->prepare($doctorQuery);
        $stmt->bind_param('ii', $adminId, $doctorId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $data = $result->fetch_assoc();
            $doctorEmail = $data['email'];
            $doctorName = $data['name'];
            $verifiedBy = $data['admin_name'] ?: 'Administrator';

            // Initialize mailer
            $mailer = new Mailer($siteUrl);

            // Send verification email
            if ($mailer->sendVerificationEmail($doctorEmail, $doctorName, $verifiedBy)) {
                return true;
            } else {
                error_log("Failed to send verification email to: $doctorEmail");
                return false;
            }
        } else {
            error_log("Doctor not found with ID: $doctorId");
            return false;
        }
    } catch (Exception $e) {
        error_log("Error in sendDoctorVerificationEmail: " . $e->getMessage());
        return false;
    }
}

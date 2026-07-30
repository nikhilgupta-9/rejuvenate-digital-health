<?php
include_once "../config/connect.php";
include_once "../util/function.php";
require_once "../util/mail_config.php"; // adjust path to wherever Mailer.php actually lives

$logo = get_header_logo();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("SELECT id, name FROM school_members WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();

        if ($member) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $upd = $conn->prepare("UPDATE school_members SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $upd->bind_param('ssi', $token, $expiresAt, $member['id']);
            $upd->execute();

            $link = BASE_URL . "school/set-password.php?token=" . $token;

            // Send via the Mailer class instead of raw mail()
            $mailer = new Mailer();
            $sent = $mailer->sendPasswordReset($email, $member['name'], $link);

            if (!$sent) {
                // Log so you can see delivery failures without exposing them to the user
                error_log("[set-password-request] Failed to send link to $email");
            }
        }

        // Same message whether the email exists or not, so we don't leak who's registered.
        $message = "If this email is in our records, we've sent a link to set your password.";
    } else {
        $message = "Please enter a valid email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Your Password | Rejuvenate Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
</head>

<body>
    <?php include("../header.php") ?>

    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="reg-card">

                        <div class="text-center mb-4">
                            <img src="<?= BASE_URL . $logo ?>" class="img-fluid mb-3" style="max-height:48px;">
                            <h4 class="fw-bold mb-1">Set Your Password</h4>
                            <p class="text-muted" style="font-size:.83rem;">Enter the email or mobile no. your school
                                registered for you and
                                we'll send you a link to set your password.</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-info" style="border-radius:10px;font-size:.85rem;">
                                <i class="fa fa-info-circle me-2"></i><?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" autocomplete="off">
                            <div class="field-group">
                                <label>Email or Mobile Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" style="border-radius:10px 0 0 10px;"><i
                                            class="fa fa-envelope"></i></span>
                                    <input type="text" class="form-control" name="email"
                                        placeholder="email or mobile no." required style="border-radius:0 10px 10px 0;">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold"
                                style="border-radius:10px;font-size:.95rem;">
                                <i class="fa fa-paper-plane me-2"></i>Send Link
                            </button>

                            <p class="text-center mt-3 mb-0" style="font-size:.82rem;">
                                <a href="<?= BASE_URL ?>school-login.php">Back to Login</a>
                            </p>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("../footer.php") ?>
</body>

</html>
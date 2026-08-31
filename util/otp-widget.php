<?php
/**
 * util/otp-widget.php — render the reusable mobile-OTP gate.
 *
 *   require_once __DIR__ . '/util/otp-widget.php';
 *   render_otp_widget([
 *     'role'            => 'patient',
 *     'mobile_field'    => 'mobile',
 *     'email_field'     => 'email',        // optional
 *     'name_field'      => 'name',         // optional
 *     'submit_selector' => '#signupForm button[type=submit]',
 *     'allow_existing'  => false,          // true for staff add-patient flows
 *     'send_url'        => BASE_URL . 'ajax/register-send-otp.php',   // optional override
 *     'verify_url'      => BASE_URL . 'ajax/register-verify-otp.php', // optional override
 *   ]);
 *
 * Emits the shared CSS + <script> only once per page.
 */

function render_otp_widget(array $o): void
{
    static $assetsDone = false;

    $base   = defined('BASE_URL') ? BASE_URL : '/';
    $role   = htmlspecialchars($o['role'] ?? 'patient');
    $mobile = htmlspecialchars($o['mobile_field'] ?? 'mobile');
    $email  = htmlspecialchars($o['email_field'] ?? '');
    $name   = htmlspecialchars($o['name_field'] ?? '');
    $submit = htmlspecialchars($o['submit_selector'] ?? '');
    $token  = htmlspecialchars($o['token_field'] ?? 'mobile_verify_token');
    $allow  = !empty($o['allow_existing']) ? '1' : '0';
    $opt    = !empty($o['optional']) ? '1' : '0';
    $send   = htmlspecialchars($o['send_url']   ?? ($base . 'ajax/register-send-otp.php'));
    $verify = htmlspecialchars($o['verify_url'] ?? ($base . 'ajax/register-verify-otp.php'));

    if (!$assetsDone) {
        $assetsDone = true;
        ?>
        <style>
        .otp-widget{margin:8px 0 4px;}
        .otp-widget .otp-w-verify{display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap;}
        .otp-widget .otp-w-code{max-width:150px;letter-spacing:4px;text-align:center;font-weight:600;}
        .otp-widget .otp-w-msg{font-size:.8rem;margin-top:6px;color:#6b7280;}
        .otp-widget .otp-w-msg-ok{color:#15803d;}
        .otp-widget .otp-w-msg-err{color:#dc2626;}
        .otp-widget .otp-w-resend{padding-left:0;padding-right:0;}
        </style>
        <script src="<?= $base ?>assets/js/otp-widget.js" defer></script>
        <?php
    }
    ?>
    <div class="otp-widget" data-otp-widget
         data-role="<?= $role ?>"
         data-send-url="<?= $send ?>"
         data-verify-url="<?= $verify ?>"
         data-mobile-field="<?= $mobile ?>"
         data-email-field="<?= $email ?>"
         data-name-field="<?= $name ?>"
         data-submit-selector="<?= $submit ?>"
         data-token-field="<?= $token ?>"
         data-allow-existing="<?= $allow ?>"
         data-optional="<?= $opt ?>">
      <button type="button" class="otp-w-send btn btn-outline-primary btn-sm">Send OTP (WhatsApp + Email)</button>
      <div class="otp-w-verify" style="display:none;">
        <input type="text" class="otp-w-code form-control form-control-sm" maxlength="6"
               inputmode="numeric" autocomplete="one-time-code" placeholder="------">
        <button type="button" class="otp-w-verify-btn btn btn-primary btn-sm">Verify</button>
        <button type="button" class="otp-w-resend btn btn-link btn-sm" disabled>
          Resend (<span class="otp-w-timer">60</span>s)
        </button>
      </div>
      <div class="otp-w-msg" role="status"></div>
      <input type="hidden" name="<?= $token ?>" value="">
    </div>
    <?php
}

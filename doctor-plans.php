<?php
include_once "config/connect.php";
include_once "util/function.php";
require_once __DIR__ . '/util/doctor-plans-render.php';
require_once __DIR__ . '/lib/JWT.php';

$contact = contact_us();
$logo    = get_header_logo();

/* Active plans, ordered for display */
$plans = [];
$res = $conn->query("SELECT * FROM doctor_plans WHERE is_active = 1 ORDER BY sort_order ASC, price ASC, id ASC");
if ($res) { while ($r = $res->fetch_assoc()) $plans[] = $r; }

/* Is a doctor logged in? -> show the subscribe CTA + Razorpay flow */
$doctor_logged_in = false;
$secret = defined('JWT_SECRET') ? JWT_SECRET : '';
if ($secret && !empty($_COOKIE['rdh_doctor_token'])) {
    try {
        $p = JWT::verify($_COOKIE['rdh_doctor_token'], $secret);
        $doctor_logged_in = (($p['role'] ?? '') === 'doctor');
    } catch (Throwable $e) {
        $doctor_logged_in = false;
    }
}

$page_url   = rtrim(BASE_URL, '/') . '/doctor-plans/';
$meta_desc  = 'REJUVENATE Digital Health doctor membership plans — list your practice online across India. Get a verified ABHA / HPR-compliant profile, online + in-clinic appointment bookings, secure video consultations and digital prescriptions. Monthly, quarterly, 6-month and yearly plans.';

/* JSON-LD */
$offers = [];
foreach ($plans as $pl) {
    $offers[] = [
        '@type' => 'Offer',
        'name'  => $pl['name'],
        'price' => (string) (float) $pl['price'],
        'priceCurrency' => 'INR',
        'description' => trim(($pl['tagline'] ?? '') . ' — billed once every ' . (int) $pl['billing_cycle_days'] . ' days.'),
    ];
}
$jsonld = [
    '@context' => 'https://schema.org',
    '@type'    => 'Product',
    'name'     => 'REJUVENATE Digital Health — Doctor Membership',
    'description' => $meta_desc,
    'brand'    => ['@type' => 'Brand', 'name' => 'REJUVENATE Digital Health'],
    'areaServed' => ['@type' => 'Country', 'name' => 'India'],
    'offers'   => ['@type' => 'AggregateOffer', 'priceCurrency' => 'INR',
        'lowPrice' => (string) (float) (min(array_map(fn($p) => (float) $p['price'], $plans ?: [['price' => 0]]))),
        'highPrice' => (string) (float) (max(array_map(fn($p) => (float) $p['price'], $plans ?: [['price' => 0]]))),
        'offers' => $offers],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="REJUVENATE Digital Health">
    <meta name="description" content="<?= htmlspecialchars($meta_desc) ?>">
    <meta name="keywords" content="doctor membership plan India, list your practice online India, online doctor platform India, doctor registration India, ABHA HPR compliant doctor listing, telemedicine doctor platform India, doctor profile listing Delhi Mumbai Bangalore Hyderabad Chennai Kolkata Pune Ahmedabad Jaipur Lucknow, video consultation platform for doctors, digital prescription platform India, ABDM doctor onboarding">
    <link rel="canonical" href="<?= htmlspecialchars($page_url) ?>">
    <title>Doctor Membership Plans — List Your Practice Online Across India | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/magnific-popup.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/meanmenu.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nice-select.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <script type="application/ld+json"><?= json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <style>
        .dp-shell{background:#f6f9fc;padding:44px 0 66px;}
        .dp-intro{max-width:820px;margin:0 auto 34px;text-align:center;}
        .dp-intro h2{font-size:1.9rem;font-weight:800;color:#1f2937;margin-bottom:12px;}
        .dp-intro p{color:#4b5563;font-size:.95rem;}
        .dp-regions{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin:16px auto 34px;max-width:760px;}
        .dp-regions span{background:#eaf4fd;color:#0C74C5;border-radius:20px;padding:4px 12px;font-size:.78rem;font-weight:600;}
        .dp-faq{max-width:820px;margin:44px auto 0;}
        .dp-faq h3{font-size:1.4rem;font-weight:800;color:#1f2937;text-align:center;margin-bottom:20px;}
        .dp-faq details{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px;margin-bottom:10px;}
        .dp-faq summary{font-weight:700;color:#1f2937;cursor:pointer;font-size:.92rem;}
        .dp-faq p{margin:10px 0 0;color:#4b5563;font-size:.88rem;}
        .dp-cta-final{text-align:center;margin-top:40px;}
        .dp-cta-final a{display:inline-block;margin:6px;padding:12px 28px;border-radius:10px;font-weight:700;text-decoration:none;}
        .dp-cta-final .primary{background:#0C74C5;color:#fff;}
        .dp-cta-final .ghost{border:1.5px solid #0C74C5;color:#0C74C5;}
    </style>
</head>
<body>
<?php include "header.php" ?>

<div class="breadcrumb-wrapper bg-cover" style="background-image:url('<?= BASE_URL ?>assets/img/inner/breadcrumb-img.jpg');">
    <div class="container">
        <div class="page-heading">
            <div class="breadcrumb-items-area">
                <div class="breadcrumb-sub-title"><h1 class="text-white wow fadeInUp" data-wow-delay=".3s">Doctor Membership Plans</h1></div>
                <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                    <li><a href="<?= BASE_URL ?>">Home</a></li><li>//</li>
                    <li><a href="<?= BASE_URL ?>doctor-network/">Doctor Network</a></li><li>//</li>
                    <li>Membership Plans</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<section class="dp-shell">
    <div class="container">

        <div class="dp-intro">
            <h2>Choose a Plan. Grow Your Practice.</h2>
            <p>
                REJUVENATE Digital Health lists verified doctors <strong>across India</strong> on one
                ABHA / ABDM-compliant platform. Pick the membership length that suits you &mdash;
                <strong>Monthly, Quarterly, 6-Month or Yearly</strong>. Longer plans cost less per month and
                keep your profile discoverable for longer, so patients searching your specialty and city can
                find and book you.
            </p>
            <div class="dp-regions">
                <span>Delhi NCR</span><span>Mumbai</span><span>Bengaluru</span><span>Hyderabad</span>
                <span>Chennai</span><span>Kolkata</span><span>Pune</span><span>Ahmedabad</span>
                <span>Jaipur</span><span>Lucknow</span><span>Chandigarh</span><span>Indore</span>
                <span>&amp; every city, town &amp; district in India</span>
            </div>
        </div>

        <?php render_doctor_plan_cards($plans, [
            'cta_mode'   => $doctor_logged_in ? 'subscribe' : 'link',
            'signup_url' => BASE_URL . 'doctor-signup.php',
        ]); ?>

        <?php if (!$doctor_logged_in): ?>
        <p class="text-center mt-3" style="font-size:.86rem;color:#6b7280;">
            Already registered? <a href="<?= BASE_URL ?>doctor-login.php" style="color:#0C74C5;font-weight:600;">Log in</a> to subscribe.
            New here? <a href="<?= BASE_URL ?>doctor-signup.php" style="color:#0C74C5;font-weight:600;">Join the Doctor Network</a> first.
        </p>
        <?php endif; ?>

        <div class="dp-faq">
            <h3>Frequently Asked Questions</h3>
            <details>
                <summary>How do patients find me after I subscribe?</summary>
                <p>Once your profile is verified and your membership is active, you appear on the relevant
                department and search pages for your specialty and city. Patients book you directly — online
                or in-clinic — and every consultation is recorded against their ABHA health record.</p>
            </details>
            <details>
                <summary>Is REJUVENATE available in my city?</summary>
                <p>Yes. It is a pan-India platform — doctors from any city, town or district can register and be
                listed. Patients searching your specialty in your location will see your profile.</p>
            </details>
            <details>
                <summary>Is there a joining or listing fee?</summary>
                <p>No. Registration and profile verification are free. You only pay for a membership plan when
                you're ready to go live and be discoverable to patients.</p>
            </details>
            <details>
                <summary>What's the difference between the plans?</summary>
                <p>Only the length and the price. A longer plan keeps your profile active for longer and works
                out cheaper per month. All plans include the same features — verified listing, online &amp;
                in-clinic bookings, video consultations and ABHA-linked digital prescriptions.</p>
            </details>
            <details>
                <summary>What happens when my plan expires?</summary>
                <p>Your public listing pauses until you renew. Your account, patient records and history stay
                intact — renew any time from your doctor dashboard and you're live again.</p>
            </details>
            <details>
                <summary>Can I upgrade to a longer plan later?</summary>
                <p>Yes. You can move to a longer plan at renewal. Time already paid for is carried forward, so
                you never lose days.</p>
            </details>
        </div>

        <div class="dp-cta-final">
            <?php if ($doctor_logged_in): ?>
                <a class="primary" href="<?= BASE_URL ?>doctor/doctor-dashboard.php">Go to My Dashboard</a>
            <?php else: ?>
                <a class="primary" href="<?= BASE_URL ?>doctor-signup.php">Register as a Doctor</a>
                <a class="ghost" href="<?= BASE_URL ?>doctor-login.php">Doctor Login</a>
            <?php endif; ?>
            <a class="ghost" href="<?= BASE_URL ?>doctor-network/">About the Doctor Network</a>
        </div>

    </div>
</section>

<?php include "footer.php" ?>

<?php if ($doctor_logged_in && $plans): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const BASE_URL = '<?= BASE_URL ?>';
    const msg = document.getElementById('dpcSubscribeMsg');
    function say(t, kind) { if (msg) { msg.textContent = t || ''; msg.className = 'dpc-msg text-' + (kind || 'muted'); } }

    document.querySelectorAll('.dpc-subscribe').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const planId = this.dataset.planId;
            const label = this.textContent;
            btn.disabled = true; this.textContent = 'Preparing payment…';
            const done = () => { btn.disabled = false; btn.textContent = label; };
            const fd = new FormData(); fd.append('plan_id', planId);

            fetch(BASE_URL + 'doctor/create-subscription-order.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(order => {
                    done();
                    if (!order.success) { say(order.message || 'Could not start the payment.', 'danger'); return; }
                    const rzp = new Razorpay({
                        key: order.key_id, order_id: order.order_id, amount: order.amount, currency: order.currency,
                        name: 'Rejuvenate Digital Health',
                        description: order.plan_name + ' — Doctor Membership',
                        theme: { color: '#0C74C5' },
                        handler: function (resp) {
                            const v = new FormData();
                            v.append('razorpay_order_id', resp.razorpay_order_id);
                            v.append('razorpay_payment_id', resp.razorpay_payment_id);
                            v.append('razorpay_signature', resp.razorpay_signature);
                            fetch(BASE_URL + 'doctor/verify-subscription-payment.php', { method: 'POST', body: v })
                                .then(r => r.json())
                                .then(res => {
                                    if (res.success) { window.location = BASE_URL + 'doctor/doctor-dashboard.php'; }
                                    else { say(res.message || 'Payment verification failed.', 'danger'); }
                                });
                        },
                        modal: { ondismiss: function () { say('Payment was cancelled.', 'muted'); } },
                    });
                    rzp.on('payment.failed', function () { say('Payment failed. Please try again.', 'danger'); });
                    rzp.open();
                })
                .catch(function () { done(); say('Network error. Please try again.', 'danger'); });
        });
    });
})();
</script>
<?php endif; ?>
</body>
</html>

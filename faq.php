<?php
include_once "config/connect.php";
include_once "util/function.php";

$contact = contact_us();
$faqs = faq_all();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description"
        content="Frequently Asked Questions for REJUVENATE Digital Health - Find answers to common questions about our services.">
    <title>FAQ | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/font-awesome.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/css/magnific-popup.css">
    <link rel="stylesheet" href="assets/css/meanmenu.css">
    <link rel="stylesheet" href="assets/css/odometer.css">
    <link rel="stylesheet" href="assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="assets/css/nice-select.css">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<style>
    p {
        color: #fff;
    }

    .faq-search-box {
        max-width: 620px;
        margin: 0 auto 40px;
        position: relative;
    }

    .faq-search-box input {
        width: 100%;
        padding: 16px 20px 16px 52px;
        border-radius: 50px;
        border: 2px solid #e5e7eb;
        font-size: 1rem;
        color: #1f2937;
        transition: .2s;
    }

    .faq-search-box input:focus {
        outline: none;
        border-color: var(--theme-color);
        box-shadow: 0 0 0 4px rgba(12, 116, 197, .12);
    }

    .faq-search-box i {
        position: absolute;
        left: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 1.05rem;
    }

    .faq-result-count {
        text-align: center;
        color: #6b7280;
        font-size: .85rem;
        margin-bottom: 24px;
    }

    .faq-empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #6b7280;
    }

    .faq-empty-state i {
        font-size: 2.4rem;
        color: #d1d5db;
        margin-bottom: 14px;
        display: block;
    }

    .faq-accordion .accordion-item {
        border: 1px solid #ececec;
        border-radius: 14px !important;
        overflow: hidden;
        transition: box-shadow .2s, border-color .2s;
    }

    .faq-accordion .accordion-item:hover {
        border-color: var(--theme-color);
        box-shadow: 0 6px 20px rgba(12, 116, 197, .08);
    }

    .faq-accordion .accordion-button {
        display: flex;
        align-items: center;
        gap: 16px;
        font-weight: 600;
        font-size: 1rem;
        color: #1f2937;
        padding: 18px 22px;
        background: #fff;
        box-shadow: none !important;
    }

    .faq-accordion .accordion-button:not(.collapsed) {
        color: var(--theme-color);
        background: #f0f7fc;
    }

    .faq-num-badge {
        flex-shrink: 0;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--theme-color-2);
        color: #fff;
        font-size: .78rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .faq-accordion .accordion-button:not(.collapsed) .faq-num-badge {
        background: var(--theme-color);
    }

    .faq-accordion .accordion-button::after {
        margin-left: auto;
        background-image: none;
        font-family: "Font Awesome 5 Pro", "FontAwesome";
        content: "\f067";
        font-weight: 400;
        font-size: .85rem;
        color: #9ca3af;
        transition: transform .25s;
    }

    .faq-accordion .accordion-button:not(.collapsed)::after {
        content: "\f068";
        color: var(--theme-color);
        transform: rotate(180deg);
    }

    .faq-accordion .accordion-body {
        padding: 4px 22px 20px 68px;
        color: #4b5563;
        font-size: .92rem;
        line-height: 1.7;
    }

    .faq-help-card {
        background: linear-gradient(145deg, var(--theme-color), #0a5f9e);
        border-radius: 16px;
        padding: 30px 26px;
        color: #fff;
        position: sticky;
        top: 24px;
    }

    .faq-help-card h5 {
        color: #fff;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .faq-help-card p {
        color: rgba(255, 255, 255, .85);
        font-size: .88rem;
        margin-bottom: 22px;
    }

    .faq-help-link {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #fff;
        padding: 12px 0;
        border-top: 1px solid rgba(255, 255, 255, .2);
        text-decoration: none;
    }

    .faq-help-link:first-of-type {
        border-top: none;
    }

    .faq-help-link:hover {
        color: #fff;
        opacity: .85;
    }

    .faq-help-link .icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .15);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .faq-help-link small {
        display: block;
        color: rgba(255, 255, 255, .7);
        font-size: .72rem;
    }

    @media (max-width: 991px) {
        .faq-help-card {
            position: static;
            margin-top: 24px;
        }
    }
</style>

<body>
    <?php include("header.php") ?>

    <!-- Breadcrumb Section Start -->
    <div class="breadcrumb-wrapper bg-cover"
        style="background-image: url('<?= BASE_URL ?>assets/img/inner/breadcrumb-img.jpg');">
        <div class="container">
            <div class="page-heading">
                <div class="breadcrumb-items-area">
                    <div class="breadcrumb-sub-title">
                        <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">Frequently Asked Questions</h1>
                    </div>
                    <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                        <li>
                            <a href="<?= BASE_URL ?>">Home</a>
                        </li>
                        <li>//</li>
                        <li>FAQ</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Content Section Start -->
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="text-center">
                <h3 class="mb-2">Frequently Asked Questions</h3>
                <p class="text-muted mb-4">Find answers to the most common questions about REJUVENATE Digital
                    Health.</p>

                <?php if (!empty($faqs)): ?>
                    <div class="faq-search-box">
                        <i class="far fa-search"></i>
                        <input type="text" id="faqSearchInput" placeholder="Search your question..."
                            onkeyup="filterFaqs()" autocomplete="off">
                    </div>
                    <div class="faq-result-count" id="faqResultCount">Showing all <?= count($faqs) ?> questions</div>
                <?php endif; ?>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="faq-accordion">
                        <?php if (empty($faqs)): ?>
                            <div class="faq-empty-state">
                                <i class="far fa-comment-question"></i>
                                No FAQs available right now. Please check back later.
                            </div>
                        <?php else: ?>
                            <div class="accordion" id="faqPageAccordion">
                                <?php $index = 0;
                                foreach ($faqs as $faq):
                                    $index++;
                                    $headingId = "faqHeading" . $index;
                                    $collapseId = "faqCollapse" . $index;
                                ?>
                                    <div class="accordion-item mb-3" data-faq-question="<?= htmlspecialchars(strtolower($faq['question'])) ?>">
                                        <h5 class="accordion-header" id="<?= $headingId ?>">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                                                aria-expanded="false" aria-controls="<?= $collapseId ?>">
                                                <span class="faq-num-badge"><?= $index ?></span>
                                                <?= htmlspecialchars($faq['question']) ?>
                                            </button>
                                        </h5>
                                        <div id="<?= $collapseId ?>" class="accordion-collapse collapse"
                                            aria-labelledby="<?= $headingId ?>"
                                            data-bs-parent="#faqPageAccordion">
                                            <div class="accordion-body">
                                                <?= nl2br(htmlspecialchars($faq['answer'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="faq-empty-state" id="faqNoMatch" style="display:none;">
                                <i class="far fa-face-frown"></i>
                                No questions match your search. Try a different keyword.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="faq-help-card">
                        <h5>Still have questions?</h5>
                        <p>Can't find the answer you're looking for? Our team is here to help.</p>
                        <a href="tel:+91-<?= $contact['phone'] ?>" class="faq-help-link">
                            <span class="icon"><i class="far fa-phone-alt"></i></span>
                            <span>
                                <small>Call Us</small>
                                +91-<?= $contact['phone'] ?>
                            </span>
                        </a>
                        <a href="mailto:<?= $contact['email'] ?>" class="faq-help-link">
                            <span class="icon"><i class="far fa-envelope"></i></span>
                            <span>
                                <small>Email Us</small>
                                <?= $contact['email'] ?>
                            </span>
                        </a>
                        <a href="<?= BASE_URL ?>contact-us.php" class="faq-help-link">
                            <span class="icon"><i class="far fa-comments"></i></span>
                            <span>
                                <small>Get in touch</small>
                                Contact Us
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact-info Section Start -->
    <section class="contact-info-section section-padding pt-5 pb-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="contact-info-box-items">
                        <div class="icon"><i class="far fa-phone-alt"></i></div>
                        <div class="content">
                            <h6>Call Us</h6>
                            <a href="tel:+91-<?= $contact['phone'] ?>">+91-<?= $contact['phone'] ?></a><br>
                            <a href="tel:+91-<?= $contact['wp_number'] ?>">+91-<?= $contact['wp_number'] ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="contact-info-box-items">
                        <div class="icon"><i class="far fa-envelope"></i></div>
                        <div class="content">
                            <h6>Send Email</h6>
                            <a href="mailto:<?= $contact['email'] ?>"><?= $contact['email'] ?></a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php include("footer.php") ?>

    <script>
        function filterFaqs() {
            const query = document.getElementById('faqSearchInput').value.trim().toLowerCase();
            const items = document.querySelectorAll('#faqPageAccordion .accordion-item');
            const noMatch = document.getElementById('faqNoMatch');
            const countEl = document.getElementById('faqResultCount');
            let visible = 0;

            items.forEach(item => {
                const match = item.dataset.faqQuestion.includes(query);
                item.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            if (noMatch) noMatch.style.display = visible === 0 ? 'block' : 'none';
            if (countEl) {
                countEl.textContent = query === ''
                    ? `Showing all ${items.length} questions`
                    : `Showing ${visible} of ${items.length} questions`;
            }
        }
    </script>
</body>

</html>

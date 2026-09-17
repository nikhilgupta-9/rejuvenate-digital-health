<?php
include_once "config/connect.php";
include_once "util/function.php";

$alias = trim($_GET['alias'] ?? '');
if ($alias === '') {
    header("Location: " . BASE_URL . "404.php");
    exit();
}

$post = fetch_blog_detail($alias);
$related = get_related_blogs($post['category'] ?? '', $post['id']);

$page_title = $post['meta_title'] ?: $post['title'];
$page_desc = $post['meta_description'] ?: ($post['excerpt'] ?: mb_strimwidth(strip_tags($post['content']), 0, 160, '…'));

$has_image = !empty($post['image']) && file_exists(__DIR__ . '/admin/uploads/blogs/' . $post['image']);
$image_url = $has_image ? BASE_URL . 'admin/uploads/blogs/' . rawurlencode($post['image']) : '';
$canonical = BASE_URL . 'blogs/' . $post['slug_url'] . '/';

$tag_list = [];
if (!empty($post['tags'])) {
    $tag_list = array_filter(array_map('trim', explode(',', $post['tags'])));
}

$categories = get_blog_categories();
$recent_posts = get_related_blogs('', $post['id'], 5);
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="<?= htmlspecialchars($page_desc) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">

    <meta property="og:type" content="article">
    <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_desc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <?php if ($has_image): ?><meta property="og:image" content="<?= htmlspecialchars($image_url) ?>"><?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">

    <title><?= htmlspecialchars($page_title) ?> — REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/magnific-popup.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/meanmenu.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/odometer.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nice-select.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <style>
        .post-title {
            font-size: 2.1rem;
            line-height: 1.3;
            font-weight: 700;
        }

        @media (max-width: 767px) {
            .post-title {
                font-size: 1.6rem;
            }
        }

        @media (max-width: 470px) {
            .post-title {
                font-size: 1.35rem;
            }
        }

        .post-hero-img {
            width: 100%;
            max-height: 380px;
            object-fit: cover;
            border-radius: 14px;
            margin-bottom: 32px;
        }

        .post-hero-fallback {
            width: 100%;
            height: 320px;
            border-radius: 14px;
            background: #f0f6fb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 32px;
        }

        .post-hero-fallback i {
            font-size: 3.5rem;
            color: #0C74C5;
            opacity: .5;
        }

        .post-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            color: #6b7280;
            font-size: .92rem;
            margin-bottom: 18px;
        }

        .post-meta-row span i {
            color: #0C74C5;
            margin-right: 6px;
        }

        .post-cat-badge {
            display: inline-block;
            background: #e8f3fc;
            color: #0C74C5;
            font-size: .78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            padding: 5px 14px;
            border-radius: 50px;
            margin-bottom: 16px;
        }

        .post-body {
            font-size: 1.05rem;
            line-height: 1.85;
            color: #374151;
        }

        .post-body h1, .post-body h2, .post-body h3, .post-body h4 {
            color: #14171f;
            margin-top: 1.6em;
            margin-bottom: .7em;
            font-weight: 700;
            line-height: 1.35;
        }

        .post-body h1 { font-size: 1.65rem; }
        .post-body h2 { font-size: 1.4rem; }
        .post-body h3 { font-size: 1.2rem; }
        .post-body h4 { font-size: 1.05rem; }

        .post-body ul, .post-body ol {
            padding-left: 1.3em;
            margin-bottom: 1.1em;
        }

        .post-body li {
            margin-bottom: .4em;
        }

        .post-body p {
            margin-bottom: 1.1em;
        }

        .post-body img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin: 20px 0;
        }

        .post-body a {
            color: #0C74C5;
        }

        .post-tags {
            margin-top: 34px;
            padding-top: 24px;
            border-top: 1px solid #f0f0f0;
        }

        .post-tags a {
            display: inline-block;
            background: #f3f4f6;
            color: #4b5563;
            font-size: .82rem;
            padding: 6px 14px;
            border-radius: 50px;
            margin: 0 6px 6px 0;
            text-decoration: none;
        }

        .post-tags a:hover {
            background: #0C74C5;
            color: #fff;
        }

        .post-share {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 24px;
        }

        .post-share a {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #f3f4f6;
            color: #4b5563;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s ease;
        }

        .post-share a:hover {
            background: #0C74C5;
            color: #fff;
        }

        .related-post-card {
            display: block;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(12, 116, 197, .08);
            height: 100%;
        }

        .related-post-card img {
            width: 100%;
            aspect-ratio: 16/10;
            object-fit: cover;
        }

        .related-post-card .rp-body {
            padding: 16px 18px;
        }

        .related-post-card h5 {
            font-size: 1rem;
            font-weight: 600;
            color: #14171f;
            margin-bottom: 6px;
        }

        .related-post-card:hover h5 {
            color: #0C74C5;
        }

        .blog-sidebar {
            position: sticky;
            top: 24px;
        }

        .sidebar-widget {
            background: #fff;
            border: 1px solid #eef1f4;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 6px 18px rgba(12, 116, 197, .06);
        }

        .sidebar-widget h6 {
            font-size: .95rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #14171f;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f6fb;
        }

        .sidebar-cat-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .sidebar-cat-list li {
            border-bottom: 1px solid #f3f4f6;
        }

        .sidebar-cat-list li:last-child {
            border-bottom: none;
        }

        .sidebar-cat-list a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 2px;
            color: #4b5563;
            font-size: .92rem;
            text-decoration: none;
            transition: color .2s ease;
        }

        .sidebar-cat-list a:hover {
            color: #0C74C5;
        }

        .sidebar-cat-list a i {
            font-size: .75rem;
            color: #c1c8d1;
        }

        .sidebar-recent-post {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 16px;
            text-decoration: none;
        }

        .sidebar-recent-post:last-child {
            margin-bottom: 0;
        }

        .sidebar-recent-thumb {
            width: 64px;
            height: 64px;
            flex: 0 0 64px;
            border-radius: 8px;
            overflow: hidden;
            background: #f0f6fb;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-recent-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-recent-thumb i {
            color: #0C74C5;
            opacity: .5;
            font-size: 1.2rem;
        }

        .sidebar-recent-post h6 {
            font-size: .88rem;
            font-weight: 600;
            color: #14171f;
            margin: 0 0 4px;
            border: none;
            padding: 0;
            text-transform: none;
            letter-spacing: normal;
            line-height: 1.4;
        }

        .sidebar-recent-post:hover h6 {
            color: #0C74C5;
        }

        .sidebar-recent-post small {
            color: #9ca3af;
            font-size: .76rem;
        }

        .sidebar-cta {
            background: linear-gradient(135deg, #0C74C5, #0a5da0);
            border-radius: 12px;
            padding: 28px 22px;
            text-align: center;
            color: #fff;
            margin-bottom: 24px;
        }

        .sidebar-cta i {
            font-size: 1.8rem;
            margin-bottom: 10px;
            opacity: .9;
        }

        .sidebar-cta h6 {
            color: #fff;
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 8px;
            border: none;
            padding: 0;
            text-transform: none;
            letter-spacing: normal;
        }

        .sidebar-cta p {
            font-size: .85rem;
            opacity: .9;
            margin-bottom: 16px;
        }

        .sidebar-cta a {
            display: inline-block;
            background: #fff;
            color: #0C74C5;
            font-weight: 600;
            font-size: .88rem;
            padding: 10px 22px;
            border-radius: 50px;
            text-decoration: none;
            transition: transform .2s ease;
        }

        .sidebar-cta a:hover {
            transform: translateY(-2px);
            color: #0a5da0;
        }

        @media (max-width: 991px) {
            .blog-sidebar {
                position: static;
                margin-top: 32px;
            }
        }
    </style>
</head>

<body>
    <?php include("header.php") ?>

    <section class="service-details-section section-padding pt-0 pb-5">
        <div class="container my-1">
            <div class="con-line">
                <ul>
                    <li><a href="<?= BASE_URL ?>">Home</a></li>
                    <li><a href="<?= BASE_URL ?>blogs/">Blog</a></li>
                    <li><a href="#"><?= htmlspecialchars($post['title']) ?></a></li>
                </ul>
            </div>

            <div class="row">
                <div class="col-lg-8">

                    <?php if (!empty($post['category'])): ?>
                        <span class="post-cat-badge"><?= htmlspecialchars($post['category']) ?></span>
                    <?php endif; ?>

                    <h1 class="post-title mb-3"><?= htmlspecialchars($post['title']) ?></h1>

                    <div class="post-meta-row">
                        <span><i class="fal fa-user"></i><?= htmlspecialchars($post['author'] ?: 'Rejuvenate Team') ?></span>
                        <span><i class="fal fa-calendar"></i><?= date('d M Y', strtotime($post['created_at'])) ?></span>
                        <?php if (!empty($post['updated_at']) && $post['updated_at'] !== $post['created_at']): ?>
                            <span><i class="fal fa-history"></i>Updated <?= date('d M Y', strtotime($post['updated_at'])) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($has_image): ?>
                        <img src="<?= htmlspecialchars($image_url) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="post-hero-img">
                    <?php else: ?>
                        <div class="post-hero-fallback"><i class="fas fa-notes-medical"></i></div>
                    <?php endif; ?>

                    <div class="post-body">
                        <?= $post['content'] ?>
                    </div>

                    <?php if (!empty($tag_list)): ?>
                        <div class="post-tags">
                            <?php foreach ($tag_list as $tag): ?>
                                <a href="<?= BASE_URL ?>blogs/"><i class="fas fa-tag"></i> <?= htmlspecialchars($tag) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="post-share">
                        <span class="text-muted small me-2">Share:</span>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonical) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://twitter.com/intent/tweet?url=<?= urlencode($canonical) ?>&text=<?= urlencode($post['title']) ?>" target="_blank" rel="noopener" aria-label="Share on X"><i class="fab fa-x-twitter"></i></a>
                        <a href="https://api.whatsapp.com/send?text=<?= urlencode($post['title'] . ' ' . $canonical) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($canonical) ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>

                </div>

                <div class="col-lg-4">
                    <div class="blog-sidebar">

                        <div class="sidebar-cta">
                            <i class="fas fa-stethoscope"></i>
                            <h6>Need to consult a doctor?</h6>
                            <p>Book an online consultation with a verified Rejuvenate doctor in minutes.</p>
                            <a href="<?= BASE_URL ?>book-appointment/">Book Appointment</a>
                        </div>

                        <?php if (!empty($categories)): ?>
                            <div class="sidebar-widget">
                                <h6>Categories</h6>
                                <ul class="sidebar-cat-list">
                                    <?php foreach ($categories as $cat): ?>
                                        <li>
                                            <a href="<?= BASE_URL ?>blogs/?category=<?= urlencode($cat) ?>">
                                                <span><?= htmlspecialchars($cat) ?></span>
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($recent_posts)): ?>
                            <div class="sidebar-widget">
                                <h6>Recent Articles</h6>
                                <?php foreach ($recent_posts as $rp): ?>
                                    <?php $rp_has_image = !empty($rp['image']) && file_exists(__DIR__ . '/admin/uploads/blogs/' . $rp['image']); ?>
                                    <a href="<?= BASE_URL ?>blogs/<?= htmlspecialchars($rp['slug_url']) ?>/" class="sidebar-recent-post">
                                        <div class="sidebar-recent-thumb">
                                            <?php if ($rp_has_image): ?>
                                                <img src="<?= BASE_URL ?>admin/uploads/blogs/<?= htmlspecialchars($rp['image']) ?>" alt="<?= htmlspecialchars($rp['title']) ?>">
                                            <?php else: ?>
                                                <i class="fas fa-notes-medical"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h6><?= htmlspecialchars($rp['title']) ?></h6>
                                            <small><?= date('d M Y', strtotime($rp['created_at'])) ?></small>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <?php if (!empty($related)): ?>
                <div class="mt-5 pt-4">
                    <h4 class="mb-4">Related Articles</h4>
                    <div class="row g-4">
                        <?php foreach ($related as $rp): ?>
                            <?php $rp_has_image = !empty($rp['image']) && file_exists(__DIR__ . '/admin/uploads/blogs/' . $rp['image']); ?>
                            <div class="col-md-4">
                                <a href="<?= BASE_URL ?>blogs/<?= htmlspecialchars($rp['slug_url']) ?>/" class="related-post-card">
                                    <?php if ($rp_has_image): ?>
                                        <img src="<?= BASE_URL ?>admin/uploads/blogs/<?= htmlspecialchars($rp['image']) ?>" alt="<?= htmlspecialchars($rp['title']) ?>">
                                    <?php endif; ?>
                                    <div class="rp-body">
                                        <h5><?= htmlspecialchars($rp['title']) ?></h5>
                                        <small class="text-muted"><?= date('d M Y', strtotime($rp['created_at'])) ?></small>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include("footer.php") ?>
</body>

</html>

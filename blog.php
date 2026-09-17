<?php
include_once "config/connect.php";
include_once "util/function.php";

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$category = trim($_GET['category'] ?? '');
$per_page = 9;

$blog_result = get_blog($page, $per_page, $category);
$blogs = $blog_result['items'];
$total = $blog_result['total'];
$total_pages = $blog_result['total_pages'];
$page = $blog_result['page'];

$categories = get_blog_categories();
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
    <meta name="description"
        content="Health tips, patient guides and updates from Rejuvenate Digital Health — read our latest articles on telemedicine, wellness and specialist care.">
    <title>Blog — REJUVENATE Digital Health</title>
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
        .blog-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(12, 116, 197, .08);
            transition: transform .25s ease, box-shadow .25s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .blog-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 32px rgba(12, 116, 197, .16);
        }

        .blog-card-img {
            width: 100%;
            aspect-ratio: 16 / 10;
            overflow: hidden;
            background: #f0f6fb;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .blog-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .blog-card-img i {
            font-size: 2.6rem;
            color: #0C74C5;
            opacity: .55;
        }

        .blog-card-body {
            padding: 20px 22px 22px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .blog-card-cat {
            display: inline-block;
            background: #e8f3fc;
            color: #0C74C5;
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            padding: 4px 12px;
            border-radius: 50px;
            margin-bottom: 12px;
            width: fit-content;
        }

        .blog-card-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #14171f;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .blog-card-title a {
            color: inherit;
        }

        .blog-card-title a:hover {
            color: #0C74C5;
        }

        .blog-card-excerpt {
            color: #6b7280;
            font-size: .92rem;
            margin-bottom: 16px;
            flex-grow: 1;
        }

        .blog-card-meta {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: .82rem;
            color: #9ca3af;
            border-top: 1px solid #f0f0f0;
            padding-top: 14px;
        }

        .blog-card-meta i {
            margin-right: 4px;
            color: #0C74C5;
        }

        .blog-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 34px;
        }

        .blog-filter-bar a {
            padding: 8px 18px;
            border-radius: 50px;
            border: 1px solid #e5e7eb;
            color: #4b5563;
            font-size: .88rem;
            font-weight: 500;
            text-decoration: none;
            transition: all .2s ease;
        }

        .blog-filter-bar a:hover,
        .blog-filter-bar a.active {
            background: #0C74C5;
            border-color: #0C74C5;
            color: #fff;
        }

        .blog-pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 40px;
        }

        .blog-pagination a,
        .blog-pagination span {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            color: #4b5563;
            text-decoration: none;
            font-weight: 500;
        }

        .blog-pagination a:hover,
        .blog-pagination span.active {
            background: #0C74C5;
            border-color: #0C74C5;
            color: #fff;
        }

        .blog-pagination span.disabled {
            opacity: .4;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <?php include("header.php") ?>

    <!-- Breadcrumb Section Start -->
    <div class="breadcrumb-wrapper bg-cover"
        style="background-image: url('<?= BASE_URL ?>assets/img/inner/breadcrumb-img.jpg');">
        <div class="container">
            <div class="page-heading">
                <div class="breadcrumb-items-area">
                    <div class="breadcrumb-sub-title">
                        <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">Health Blog</h1>
                    </div>
                    <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
                        <li><a href="<?= BASE_URL ?>">Home</a></li>
                        <li>//</li>
                        <li>Blog</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Blog Grid Section Start -->
    <section class="cta-section section-padding fix mb-3">
        <div class="container">

            <div class="section-title mb-4 text-center">
                <span class="subtitle tz-sub-tilte tz-sub-anim text-uppercase tx-subTitle">OUR BLOG</span>
                <h2 class="service-text">Health Tips &amp; Patient Guides</h2>
                <p><?= $total ?> article<?= $total !== 1 ? 's' : '' ?> published</p>
            </div>

            <?php if (!empty($categories)): ?>
                <div class="blog-filter-bar justify-content-center">
                    <a href="<?= BASE_URL ?>blogs/" class="<?= $category === '' ? 'active' : '' ?>">All</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="<?= BASE_URL ?>blogs/?category=<?= urlencode($cat) ?>"
                            class="<?= $category === $cat ? 'active' : '' ?>"><?= htmlspecialchars($cat) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($total === 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-newspaper fa-3x text-muted mb-3 d-block" style="opacity:.3;"></i>
                    <h4 class="fw-semibold text-dark mb-2">No Articles Yet</h4>
                    <p class="text-muted">Please check back soon — we're preparing our first health articles.</p>
                </div>
            <?php else: ?>
                <div class="row g-4 pb-0">
                    <?php foreach ($blogs as $post): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="blog-card">
                                <a href="<?= BASE_URL ?>blogs/<?= htmlspecialchars($post['slug_url']) ?>/" class="blog-card-img">
                                    <?php if (!empty($post['image']) && file_exists(__DIR__ . '/admin/uploads/blogs/' . $post['image'])): ?>
                                        <img src="<?= BASE_URL ?>admin/uploads/blogs/<?= htmlspecialchars($post['image']) ?>"
                                            alt="<?= htmlspecialchars($post['title']) ?>">
                                    <?php else: ?>
                                        <i class="fas fa-notes-medical"></i>
                                    <?php endif; ?>
                                </a>
                                <div class="blog-card-body">
                                    <?php if (!empty($post['category'])): ?>
                                        <span class="blog-card-cat"><?= htmlspecialchars($post['category']) ?></span>
                                    <?php endif; ?>
                                    <h3 class="blog-card-title">
                                        <a href="<?= BASE_URL ?>blogs/<?= htmlspecialchars($post['slug_url']) ?>/"><?= htmlspecialchars($post['title']) ?></a>
                                    </h3>
                                    <p class="blog-card-excerpt">
                                        <?php
                                        $excerpt = $post['excerpt'] ?: strip_tags($post['content']);
                                        echo htmlspecialchars(mb_strimwidth($excerpt, 0, 110, '…'));
                                        ?>
                                    </p>
                                    <div class="blog-card-meta">
                                        <span><i class="fal fa-user"></i><?= htmlspecialchars($post['author'] ?: 'Rejuvenate Team') ?></span>
                                        <span><i class="fal fa-calendar"></i><?= date('d M Y', strtotime($post['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="blog-pagination">
                        <?php
                        $qs = $category !== '' ? '&category=' . urlencode($category) : '';
                        ?>
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $qs ?>"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $qs ?>"><i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </section>

    <?php include("footer.php") ?>
</body>

</html>

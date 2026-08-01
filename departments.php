<?php
include_once "config/connect.php";
include_once "util/function.php";

$departments = get_sub_category();
$total = count($departments);
$search_query = trim($_GET['search'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="modinatheme">
  <meta name="description"
    content="Browse all medical departments and specialities at Rejuvenate Digital Health — find the right specialist and book a consultation online.">
  <title>All Departments — REJUVENATE Digital Health</title>
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
    .dept-icon-fallback {
      width: 100%;
      aspect-ratio: 1.4 / 1;
      background: #f0f6fb;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .dept-icon-fallback i {
      font-size: 2.6rem;
      color: #0C74C5;
      opacity: .55;
    }

    .dept-search-box {
      max-width: 460px;
      margin: -14px auto 40px;
      position: relative;
      z-index: 2;
    }

    .dept-search-box input {
      border-radius: 50px;
      padding: 14px 22px 14px 48px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 8px 24px rgba(12, 116, 197, .12);
      width: 100%;
    }

    .dept-search-box i {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
    }

    .dept-empty-row {
      display: none;
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
            <h1 class="text-white wow fadeInUp" data-wow-delay=".3s">All Departments</h1>
          </div>
          <ul class="breadcrumb-items wow fadeInUp" data-wow-delay=".5s">
            <li><a href="<?= BASE_URL ?>">Home</a></li>
            <li>//</li>
            <li>Departments</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Departments Grid Section Start -->
  <section class="cta-section section-padding fix mb-3">
    <div class="container">

      <div class="dept-search-box">
        <i class="far fa-search"></i>
        <input type="text" id="deptSearch" class="form-control"
          placeholder="Search a department or problem, e.g. Cardiology, chest pain…"
          value="<?= htmlspecialchars($search_query) ?>">
      </div>

      <?php if ($total === 0): ?>
        <div class="text-center py-5">
          <i class="fas fa-hospital-user fa-3x text-muted mb-3 d-block" style="opacity:.3;"></i>
          <h4 class="fw-semibold text-dark mb-2">No Departments Available</h4>
          <p class="text-muted">Please check back soon — we're setting up our specialist departments.</p>
        </div>
      <?php else: ?>
        <div class="section-title mb-4">
          <span class="subtitle tz-sub-tilte tz-sub-anim text-uppercase tx-subTitle">SPECIALITIES</span>
          <h2 class="service-text">Consult Doctor by Speciality</h2>
          <p><?= $total ?> department<?= $total !== 1 ? 's' : '' ?> available — select one to view specialists</p>
        </div>

        <div class="row g-4 pb-0 advance-wrap" id="deptGrid">
          <?php foreach ($departments as $dept): ?>
            <div class="col-6 col-sm-6 col-md-4 col-lg-3 col-xl-3 dept-card"
              data-name="<?= strtolower(htmlspecialchars($dept['categories'])) ?>"
              data-desc="<?= strtolower(htmlspecialchars(strip_tags($dept['description'] ?? ''))) ?>">
              <div class="team-box-items mt-0">
                <a href="<?= BASE_URL ?>department/<?= htmlspecialchars($dept['slug_url']) ?>/">
                  <div class="team-image">
                    <?php if (!empty($dept['sub_cat_img'])): ?>
                      <img src="<?= BASE_URL ?>admin/uploads/sub-category/<?= htmlspecialchars($dept['sub_cat_img']) ?>"
                        alt="<?= htmlspecialchars($dept['categories']) ?>">
                    <?php else: ?>
                      <div class="dept-icon-fallback"><i class="fas fa-stethoscope"></i></div>
                    <?php endif; ?>
                    <span class="post-box"><?= htmlspecialchars(trim($dept['categories'])) ?></span>
                  </div>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="text-center py-5 dept-empty-row" id="deptNoResults">
          <i class="fas fa-search fa-2x text-muted mb-3 d-block" style="opacity:.3;"></i>
          <p class="text-muted mb-0">No departments match your search.</p>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <?php include("footer.php") ?>

  <script>
    const deptSearch = document.getElementById('deptSearch');
    if (deptSearch) {
      const runFilter = function () {
        const q = deptSearch.value.trim().toLowerCase();
        const cards = document.querySelectorAll('.dept-card');
        let visible = 0;
        cards.forEach(card => {
          const match = !q || card.dataset.name.includes(q) || card.dataset.desc.includes(q);
          card.style.display = match ? '' : 'none';
          if (match) visible++;
        });
        const noResults = document.getElementById('deptNoResults');
        if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
      };
      deptSearch.addEventListener('input', runFilter);
      if (deptSearch.value.trim()) runFilter();
    }
  </script>
</body>

</html>
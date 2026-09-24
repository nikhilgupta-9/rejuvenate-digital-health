<?php
include_once "config/connect.php";
include_once "util/function.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : null;

// Fetch tests from lab_tests_catalog
$tests_res = $conn->query("SELECT * FROM lab_tests_catalog WHERE status = 'Active' ORDER BY category ASC, price DESC");
$catalog_tests = $tests_res ? $tests_res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lab Investigations & Diagnostic Tests | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nice-select.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <style>
        .test-card { border:1px solid #e2e8f0; border-radius:14px; padding:22px; background:#fff; height:100%; display:flex; flex-direction:column; justify-content:space-between; transition:all .2s; }
        .test-card:hover { border-color:#0d6efd; box-shadow:0 6px 18px rgba(13,110,253,.08); transform:translateY(-2px); }
        .tag-pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:.75rem; font-weight:600; }
        .tag-fasting { background:#fef3c7; color:#92400e; }
        .tag-nofasting { background:#dcfce7; color:#166534; }
        .tag-cat { background:#e0f2fe; color:#0369a1; }
        .checkout-box { position:sticky; top:100px; background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:24px; box-shadow:0 4px 16px rgba(0,0,0,.04); }
        .cart-item { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px dashed #e2e8f0; font-size:.88rem; }
    </style>
</head>

<body>
    <?php include("header.php") ?>

    <div class="breadcrumb-wrapper bg-cover" style="background-image: url('<?= BASE_URL ?>assets/img/inner/breadcrumb-img.jpg');">
        <div class="container">
            <div class="page-heading">
                <div class="breadcrumb-items-area">
                    <div class="breadcrumb-sub-title">
                        <h1 class="text-white">Diagnostic Lab Tests & Packages</h1>
                    </div>
                    <ul class="breadcrumb-items">
                        <li><a href="<?= BASE_URL ?>">Home</a></li>
                        <li>//</li>
                        <li>Diagnostic Services</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <section class="section-padding fix bg-light">
        <div class="container">
            <div class="row g-4">
                <!-- Left: Catalog & Search -->
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                        <div>
                            <h3 class="fw-bold mb-1"><i class="fas fa-flask text-primary me-2"></i>Book Medical Lab Tests</h3>
                            <p class="text-muted small mb-0">Certified diagnostic investigations with safe home sample collection</p>
                        </div>
                        <div class="w-100 mt-2">
                            <input type="text" id="testSearchInput" class="form-control form-control-lg rounded-pill" placeholder="🔍 Search test by name (e.g. Thyroid, CBC, Lipid, HbA1c, Vitamin D)..." onkeyup="filterTests()">
                        </div>
                    </div>

                    <!-- Category Filter Buttons -->
                    <div class="d-flex flex-wrap gap-2 mb-4" id="categoryFilters">
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="setCategoryFilter('all', this)">All Tests</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="setCategoryFilter('Health Package', this)">Health Packages</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="setCategoryFilter('Biochemistry', this)">Biochemistry</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="setCategoryFilter('Pathology', this)">Pathology</button>
                    </div>

                    <div class="row g-3" id="testsGrid">
                        <?php foreach ($catalog_tests as $t): 
                            $finalPrice = $t['discount_price'] ?: $t['price'];
                        ?>
                        <div class="col-md-6 test-item" data-category="<?= htmlspecialchars($t['category']) ?>" data-name="<?= strtolower(htmlspecialchars($t['test_name'])) ?>">
                            <div class="test-card">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <span class="tag-pill tag-cat"><?= htmlspecialchars($t['category']) ?></span>
                                        <?php if ($t['fasting_required']): ?>
                                            <span class="tag-pill tag-fasting"><i class="fas fa-clock me-1"></i>Fasting Req.</span>
                                        <?php else: ?>
                                            <span class="tag-pill tag-nofasting"><i class="fas fa-check-circle me-1"></i>No Fasting</span>
                                        <?php endif; ?>
                                    </div>

                                    <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($t['test_name']) ?></h5>
                                    <p class="text-muted small mb-3"><?= htmlspecialchars($t['description'] ?: 'Standard pathology test parameters.') ?></p>
                                    
                                    <div class="d-flex gap-3 small text-muted mb-3">
                                        <div><i class="fas fa-vial text-danger me-1"></i><?= htmlspecialchars($t['sample_type']) ?></div>
                                        <div><i class="fas fa-history text-primary me-1"></i>Report in <?= (int)$t['turnaround_hours'] ?>h</div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                    <div>
                                        <span class="fs-5 fw-bold text-dark">₹<?= number_format($finalPrice, 2) ?></span>
                                        <?php if ($t['discount_price'] && $t['discount_price'] < $t['price']): ?>
                                            <span class="text-decoration-line-through text-muted small ms-1">₹<?= number_format($t['price'], 2) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="addTestToCart('<?= htmlspecialchars(addslashes($t['test_code'])) ?>', '<?= htmlspecialchars(addslashes($t['test_name'])) ?>', <?= (float)$finalPrice ?>, '<?= htmlspecialchars(addslashes($t['category'])) ?>')">
                                        <i class="fas fa-plus me-1"></i> Add
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Schedule & Checkout Box -->
                <div class="col-lg-4">
                    <div class="checkout-box">
                        <h5 class="fw-bold mb-3"><i class="fas fa-calendar-check text-primary me-2"></i>Schedule Booking</h5>
                        <div id="labAlert"></div>

                        <form id="labBookingForm" enctype="multipart/form-data">
                            <!-- Selected Tests Cart -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Selected Tests</label>
                                <div id="cartList" class="bg-light rounded p-2 text-muted small">No tests selected yet. Click "+ Add" on any test.</div>
                                <div class="d-flex justify-content-between align-items-center mt-2 fw-bold small">
                                    <span>Total Payable:</span>
                                    <span class="text-primary fs-6" id="cartTotal">₹0.00</span>
                                </div>
                            </div>

                            <!-- Prescription Upload Optional -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Have Doctor's Prescription? (Optional)</label>
                                <input type="file" name="prescription_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf">
                                <small class="text-muted" style="font-size:.72rem;">Upload if doctor prescribed custom tests</small>
                            </div>

                            <!-- Sample Collection Type -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Sample Collection Mode *</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="border rounded p-2 d-flex align-items-center gap-2 w-100" style="cursor:pointer;font-size:.82rem;">
                                            <input type="radio" name="collection_type" value="home_collection" checked onchange="toggleAddressField(true)">
                                            <span>Home Pickup</span>
                                        </label>
                                    </div>
                                    <div class="col-6">
                                        <label class="border rounded p-2 d-flex align-items-center gap-2 w-100" style="cursor:pointer;font-size:.82rem;">
                                            <input type="radio" name="collection_type" value="visit_lab" onchange="toggleAddressField(false)">
                                            <span>Visit Lab</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Patient Details -->
                            <div class="mb-2">
                                <input type="text" name="name" class="form-control form-control-sm" placeholder="Patient Full Name *" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required>
                            </div>
                            <div class="mb-2">
                                <input type="tel" name="phone" class="form-control form-control-sm" placeholder="10-digit WhatsApp Mobile *" value="<?= htmlspecialchars($_SESSION['user_mobile'] ?? '') ?>" pattern="[6-9][0-9]{9}" required>
                            </div>
                            <div class="mb-2">
                                <input type="email" name="email" class="form-control form-control-sm" placeholder="Email Address" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>">
                            </div>

                            <div id="addressSection" class="mb-2">
                                <textarea name="address" class="form-control form-control-sm" rows="2" placeholder="Home address for sample pickup *"></textarea>
                                <div class="row g-2 mt-1">
                                    <div class="col-7">
                                        <input type="text" name="city" class="form-control form-control-sm" placeholder="City *">
                                    </div>
                                    <div class="col-5">
                                        <input type="text" name="zip_code" class="form-control form-control-sm" placeholder="PIN code">
                                    </div>
                                </div>
                            </div>

                            <!-- Date and Time Slot -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Date *</label>
                                    <input type="date" name="booking_date" class="form-control form-control-sm" min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Slot *</label>
                                    <select name="time_slot" class="form-select form-select-sm" required>
                                        <option value="07:00 AM - 09:00 AM">07:00 - 09:00 AM (Fasting)</option>
                                        <option value="09:00 AM - 11:00 AM">09:00 - 11:00 AM</option>
                                        <option value="11:00 AM - 01:00 PM">11:00 AM - 01:00 PM</option>
                                        <option value="02:00 PM - 05:00 PM">02:00 - 05:00 PM</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" id="bookLabBtn" class="theme-btn btn-sm w-100 py-2">
                                <i class="fas fa-check-circle me-1"></i> Confirm Lab Booking
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("footer.php") ?>

    <script>
        const labCart = [];
        let currentCat = 'all';

        function addTestToCart(code, name, price, category) {
            if (labCart.some(t => t.test_code === code)) return;
            labCart.push({ test_code: code, test_name: name, price: price, category: category });
            renderLabCart();
        }

        function removeLabItem(code) {
            const idx = labCart.findIndex(t => t.test_code === code);
            if (idx !== -1) {
                labCart.splice(idx, 1);
                renderLabCart();
            }
        }

        function renderLabCart() {
            const container = document.getElementById('cartList');
            const totalEl = document.getElementById('cartTotal');
            if (labCart.length === 0) {
                container.innerHTML = 'No tests selected yet. Click "+ Add" on any test.';
                totalEl.innerText = '₹0.00';
                return;
            }
            let total = 0;
            let html = '';
            labCart.forEach(item => {
                total += item.price;
                html += `
                    <div class="cart-item">
                        <span><strong>${item.test_name}</strong></span>
                        <div class="d-flex align-items-center gap-2">
                            <span>₹${item.price.toFixed(2)}</span>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeLabItem('${item.test_code}')">&times;</button>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
            totalEl.innerText = '₹' + total.toFixed(2);
        }

        function toggleAddressField(isHome) {
            document.getElementById('addressSection').style.display = isHome ? 'block' : 'none';
        }

        function filterTests() {
            const query = document.getElementById('testSearchInput').value.toLowerCase();
            document.querySelectorAll('.test-item').forEach(el => {
                const name = el.getAttribute('data-name');
                const cat = el.getAttribute('data-category');
                const matchCat = (currentCat === 'all' || cat === currentCat);
                const matchQuery = (name.indexOf(query) !== -1);
                el.style.display = (matchCat && matchQuery) ? 'block' : 'none';
            });
        }

        function setCategoryFilter(cat, btn) {
            currentCat = cat;
            document.querySelectorAll('#categoryFilters button').forEach(b => {
                b.className = 'btn btn-sm btn-outline-secondary rounded-pill px-3';
            });
            btn.className = 'btn btn-sm btn-primary rounded-pill px-3';
            filterTests();
        }

        document.getElementById('labBookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('bookLabBtn');
            const alertBox = document.getElementById('labAlert');
            alertBox.innerHTML = '';

            const formData = new FormData(this);
            if (labCart.length > 0) {
                formData.append('tests', JSON.stringify(labCart));
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Scheduling Booking...';

            fetch('<?= BASE_URL ?>util/lab-booking-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Confirm Lab Booking';
                if (data.status === 'success') {
                    alertBox.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show p-3 small">
                            <h6 class="fw-bold mb-1"><i class="fas fa-check-circle me-1"></i>Booking Confirmed!</h6>
                            <p class="mb-0">${data.message}</p>
                        </div>
                    `;
                    document.getElementById('labBookingForm').reset();
                    labCart.length = 0;
                    renderLabCart();
                } else {
                    alertBox.innerHTML = `<div class="alert alert-danger small">${data.message || 'Error booking test.'}</div>`;
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Confirm Lab Booking';
                alertBox.innerHTML = `<div class="alert alert-danger small">Network error. Please try again.</div>`;
            });
        });
    </script>
</body>
</html>

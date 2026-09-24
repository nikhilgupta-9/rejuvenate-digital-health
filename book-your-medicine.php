<?php
include_once "config/connect.php";
include_once "util/function.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : null;

// Fetch patient's recent online consultation prescriptions if logged in
$recent_rx = [];
if ($user_id) {
    $rx_stmt = $conn->prepare("
        SELECT p.id, p.care_context_ref, p.visit_date, p.medications, d.name AS doctor_name
        FROM prescriptions p
        LEFT JOIN doctors d ON d.id = p.doctor_id
        WHERE p.patient_id = ? AND p.status = 'final'
        ORDER BY p.visit_date DESC LIMIT 5
    ");
    $rx_stmt->bind_param('i', $user_id);
    $rx_stmt->execute();
    $recent_rx = $rx_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $rx_stmt->close();
}

// Fetch common medicines from pharmacy_medicines
$medicines_res = $conn->query("SELECT * FROM pharmacy_medicines WHERE stock_status = 'in_stock' ORDER BY name ASC LIMIT 20");
$common_meds = $medicines_res ? $medicines_res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Online Pharmacy & Medicine Delivery | REJUVENATE Digital Health</title>
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
        .rx-card { border:1px solid #e2e8f0; border-radius:12px; padding:20px; background:#fff; transition:all .2s; }
        .rx-card:hover { border-color:#0d6efd; box-shadow:0 4px 14px rgba(13,110,253,.08); }
        .rx-badge { display:inline-block; padding:4px 10px; border-radius:6px; font-size:.8rem; font-weight:600; background:#e0f2fe; color:#0369a1; }
        .upload-dropzone { border:2px dashed #cbd5e1; border-radius:12px; padding:35px 20px; text-align:center; background:#f8fafc; cursor:pointer; }
        .upload-dropzone:hover { border-color:#0d6efd; background:#eff6ff; }
        .med-qty-btn { width:28px; height:28px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; }
        .order-step { display:flex; gap:14px; margin-bottom:20px; align-items:flex-start; }
        .step-num { width:32px; height:32px; border-radius:50%; background:#0d6efd; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0; }
    </style>
</head>

<body>
    <?php include("header.php") ?>

    <div class="breadcrumb-wrapper bg-cover" style="background-image: url('<?= BASE_URL ?>assets/img/inner/breadcrumb-img.jpg');">
        <div class="container">
            <div class="page-heading">
                <div class="breadcrumb-items-area">
                    <div class="breadcrumb-sub-title">
                        <h1 class="text-white">Online Pharmacy & Medicine Delivery</h1>
                    </div>
                    <ul class="breadcrumb-items">
                        <li><a href="<?= BASE_URL ?>">Home</a></li>
                        <li>//</li>
                        <li>Book Your Medicine</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Pharmacy Main Section -->
    <section class="section-padding fix">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 rounded-4 p-4 p-md-5">
                        <h3 class="fw-bold mb-3"><i class="fas fa-prescription-bottle-alt text-primary me-2"></i>Order Prescribed Medicines</h3>
                        <p class="text-muted mb-4">Upload your doctor's prescription or select items to order genuine medicines delivered directly to your doorstep with WhatsApp order tracking.</p>

                        <div id="rxAlert"></div>

                        <form id="pharmacyOrderForm" enctype="multipart/form-data">
                            <!-- Step 1: Upload or Choose Source -->
                            <div class="order-step">
                                <div class="step-num">1</div>
                                <div class="w-100">
                                    <h5 class="fw-bold mb-2">Prescription & Medicine Details</h5>
                                    
                                    <ul class="nav nav-pills mb-3" id="rxSourceTabs" role="tablist">
                                        <li class="nav-item">
                                            <button class="nav-link active" id="tab-upload" data-bs-toggle="pill" data-bs-target="#content-upload" type="button"><i class="fas fa-file-upload me-1"></i>Upload Prescription</button>
                                        </li>
                                        <?php if (!empty($recent_rx)): ?>
                                        <li class="nav-item">
                                            <button class="nav-link" id="tab-consultation" data-bs-toggle="pill" data-bs-target="#content-consultation" type="button"><i class="fas fa-user-md me-1"></i>From Consultation</button>
                                        </li>
                                        <?php endif; ?>
                                        <li class="nav-item">
                                            <button class="nav-link" id="tab-catalog" data-bs-toggle="pill" data-bs-target="#content-catalog" type="button"><i class="fas fa-search me-1"></i>Browse Common Medicines</button>
                                        </li>
                                    </ul>

                                    <div class="tab-content" id="rxTabContent">
                                        <!-- Tab 1: Upload File -->
                                        <div class="tab-pane fade show active" id="content-upload">
                                            <div class="upload-dropzone" onclick="document.getElementById('rxFileInput').click()">
                                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-2"></i>
                                                <p class="fw-bold mb-1">Click to Upload Prescription File</p>
                                                <small class="text-muted">Supports JPG, PNG, PDF or WEBP (Max 10 MB)</small>
                                                <input type="file" name="prescription_file" id="rxFileInput" class="d-none" accept=".jpg,.jpeg,.png,.pdf,.webp" onchange="handleFileSelected(this)">
                                                <div id="selectedFileName" class="mt-2 text-success fw-bold"></div>
                                            </div>
                                        </div>

                                        <!-- Tab 2: From Consultation -->
                                        <?php if (!empty($recent_rx)): ?>
                                        <div class="tab-pane fade" id="content-consultation">
                                            <p class="small text-muted mb-2">Select a prescription from your completed consultations:</p>
                                            <div class="list-group">
                                                <?php foreach ($recent_rx as $rx): 
                                                    $meds = json_decode($rx['medications'] ?? '[]', true) ?: [];
                                                ?>
                                                <label class="list-group-item d-flex gap-3 align-items-center">
                                                    <input class="form-check-input flex-shrink-0" type="radio" name="prescription_id" value="<?= $rx['id'] ?>">
                                                    <div>
                                                        <strong>Consultation on <?= date('d M Y', strtotime($rx['visit_date'])) ?></strong> — Dr. <?= htmlspecialchars($rx['doctor_name'] ?? 'Doctor') ?>
                                                        <div class="small text-muted"><?= count($meds) ?> medicine(s) prescribed (<?= htmlspecialchars($rx['care_context_ref']) ?>)</div>
                                                    </div>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Tab 3: Catalog Selection -->
                                        <div class="tab-pane fade" id="content-catalog">
                                            <p class="small text-muted mb-2">Select commonly prescribed medicines / health essentials:</p>
                                            <div class="row g-2" style="max-height:260px; overflow-y:auto;">
                                                <?php foreach ($common_meds as $cm): ?>
                                                <div class="col-md-6">
                                                    <div class="border rounded p-2 d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <div class="fw-bold small"><?= htmlspecialchars($cm['name']) ?></div>
                                                            <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($cm['strength']) ?> • ₹<?= number_format($cm['price'], 2) ?></div>
                                                        </div>
                                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addCatalogItem('<?= htmlspecialchars(addslashes($cm['name'])) ?>', '<?= htmlspecialchars(addslashes($cm['strength'])) ?>', <?= (float)$cm['price'] ?>)">
                                                            <i class="fas fa-plus"></i> Add
                                                        </button>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <div class="mt-3">
                                                <h6>Selected Medicines to Order:</h6>
                                                <div id="selectedMedicinesList" class="p-2 bg-light rounded text-muted small">No items added yet. Click "+ Add" above.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Step 2: Patient & Delivery Address -->
                            <div class="order-step">
                                <div class="step-num">2</div>
                                <div class="w-100">
                                    <h5 class="fw-bold mb-3">Delivery Information</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Full Name *</label>
                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Mobile Number (with WhatsApp) *</label>
                                            <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile" value="<?= htmlspecialchars($_SESSION['user_mobile'] ?? '') ?>" pattern="[6-9][0-9]{9}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Email Address</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">City *</label>
                                            <input type="text" name="city" class="form-control" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-bold">Complete Street Address *</label>
                                            <textarea name="address" class="form-control" rows="2" placeholder="House/Flat no, Landmark, Area" required></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">PIN Code</label>
                                            <input type="text" name="zip_code" class="form-control">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Special Instructions / Notes</label>
                                            <input type="text" name="notes" class="form-control" placeholder="e.g. Call before delivery, 1-month supply">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Step 3: Payment Choice -->
                            <div class="order-step">
                                <div class="step-num">3</div>
                                <div class="w-100">
                                    <h5 class="fw-bold mb-3">Payment Method</h5>
                                    <div class="row g-2">
                                        <div class="col-sm-6">
                                            <label class="border rounded p-3 d-flex align-items-center gap-3 w-100" style="cursor:pointer;">
                                                <input type="radio" name="payment_method" value="cod" checked>
                                                <div>
                                                    <div class="fw-bold"><i class="fas fa-hand-holding-usd text-success me-1"></i> Cash on Delivery (COD)</div>
                                                    <small class="text-muted">Pay when medicines are delivered</small>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="border rounded p-3 d-flex align-items-center gap-3 w-100" style="cursor:pointer;">
                                                <input type="radio" name="payment_method" value="online">
                                                <div>
                                                    <div class="fw-bold"><i class="fas fa-credit-card text-primary me-1"></i> Online Payment</div>
                                                    <small class="text-muted">UPI, Cards, NetBanking (via Razorpay)</small>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 pt-2">
                                <button type="submit" id="submitOrderBtn" class="theme-btn btn-lg w-100 py-3">
                                    <i class="fas fa-check-circle me-1"></i> Place Medicine Order
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-light">
                        <h5 class="fw-bold mb-3"><i class="fas fa-shield-alt text-primary me-2"></i>Pharmacy Guarantee</h5>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-3 small">
                            <li class="d-flex gap-2">
                                <i class="fas fa-check-circle text-success mt-1"></i>
                                <span><strong>100% Genuine Medicines</strong> sourced directly from licensed pharmaceutical distributors.</span>
                            </li>
                            <li class="d-flex gap-2">
                                <i class="fas fa-check-circle text-success mt-1"></i>
                                <span><strong>Pharmacist Verified:</strong> Every prescription is verified by a registered pharmacist before dispatch.</span>
                            </li>
                            <li class="d-flex gap-2">
                                <i class="fas fa-check-circle text-success mt-1"></i>
                                <span><strong>WhatsApp Live Tracking:</strong> Real-time status updates from order verification to delivery.</span>
                            </li>
                            <li class="d-flex gap-2">
                                <i class="fas fa-check-circle text-success mt-1"></i>
                                <span><strong>Fast Doorstep Delivery:</strong> Same-day or next-day delivery across serviceable pin codes.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <h6 class="fw-bold mb-2"><i class="fas fa-headset text-primary me-2"></i>Need Help with Medicines?</h6>
                        <p class="small text-muted mb-3">Speak with our pharmacy team for assistance with dosages, substitutes, or orders.</p>
                        <a href="https://wa.me/919027914122" target="_blank" class="btn btn-outline-success w-100">
                            <i class="fab fa-whatsapp me-1"></i> Chat with Pharmacy on WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("footer.php") ?>

    <script>
        const cartItems = [];

        function handleFileSelected(input) {
            if (input.files && input.files[0]) {
                document.getElementById('selectedFileName').innerText = 'Selected: ' + input.files[0].name;
            }
        }

        function addCatalogItem(name, strength, price) {
            const existing = cartItems.find(i => i.name === name);
            if (existing) {
                existing.qty++;
            } else {
                cartItems.push({ name, strength, price, qty: 1 });
            }
            renderCart();
        }

        function removeCartItem(idx) {
            cartItems.splice(idx, 1);
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('selectedMedicinesList');
            if (cartItems.length === 0) {
                container.innerHTML = 'No items added yet. Click "+ Add" above.';
                return;
            }
            let html = '<ul class="list-unstyled mb-0">';
            cartItems.forEach((item, idx) => {
                html += `
                    <li class="d-flex justify-content-between align-items-center mb-1">
                        <span><strong>${item.name}</strong> (${item.strength}) x ${item.qty} — ₹${(item.price * item.qty).toFixed(2)}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeCartItem(${idx})">&times;</button>
                    </li>
                `;
            });
            html += '</ul>';
            container.innerHTML = html;
        }

        document.getElementById('pharmacyOrderForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitOrderBtn');
            const alertBox = document.getElementById('rxAlert');
            alertBox.innerHTML = '';

            const formData = new FormData(this);
            if (cartItems.length > 0) {
                formData.append('items', JSON.stringify(cartItems));
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing Order...';

            fetch('<?= BASE_URL ?>util/pharmacy-order-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Place Medicine Order';
                if (data.status === 'success') {
                    alertBox.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show p-4">
                            <h5 class="fw-bold mb-1"><i class="fas fa-check-circle me-2"></i>Order Placed Successfully!</h5>
                            <p class="mb-0">${data.message}</p>
                        </div>
                    `;
                    document.getElementById('pharmacyOrderForm').reset();
                    document.getElementById('selectedFileName').innerText = '';
                    cartItems.length = 0;
                    renderCart();
                } else {
                    alertBox.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i>${data.message || 'Error submitting order.'}
                        </div>
                    `;
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Place Medicine Order';
                alertBox.innerHTML = `<div class="alert alert-danger">An unexpected error occurred. Please try again.</div>`;
            });
        });
    </script>
</body>
</html>
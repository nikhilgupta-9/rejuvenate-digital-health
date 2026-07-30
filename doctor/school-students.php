<?php
require_once __DIR__ . '/auth/guard.php';
require_once dirname(__DIR__) . '/config/connect.php';
$payload   = doctor_jwt_guard();
$doctor_id = (int) ($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$sidebar_active = 'school-students';
$err = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Find Student — REJUVENATE Doctor Portal</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <style>
        .lookup-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            padding: 26px 28px;
            max-width: 520px;
            margin: 0 auto;
        }

        .lookup-title {
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .9px;
            color: #6b7280;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f3f4f6;
        }

        .wizard {
            display: flex;
            margin-bottom: 26px;
            position: relative;
            max-width: 520px;
            margin-left: auto;
            margin-right: auto;
        }

        .wizard::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 18px;
            right: 18px;
            height: 2px;
            background: #e5e7eb;
            z-index: 0;
        }

        .wi {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .wc {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid #e5e7eb;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: .85rem;
            color: #9ca3af;
            transition: .25s;
        }

        .wi.active .wc {
            border-color: #0c74c5;
            background: #0c74c5;
            color: #fff;
        }

        .wi.done .wc {
            border-color: #16a34a;
            background: #16a34a;
            color: #fff;
        }

        .wl {
            display: block;
            font-size: .68rem;
            color: #9ca3af;
            margin-top: 4px;
            font-weight: 600;
        }

        .wi.active .wl {
            color: #0c74c5;
        }

        .wi.done .wl {
            color: #16a34a;
        }

        .sbody {
            display: none;
        }

        .sbody.active {
            display: block;
        }

        .otp-row {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 18px 0;
        }

        .otp-row input {
            width: 46px;
            height: 52px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            text-align: center;
            font-size: 1.3rem;
            font-weight: 700;
            transition: .15s;
        }

        .otp-row input:focus {
            border-color: #0c74c5;
            outline: none;
            box-shadow: 0 0 0 3px rgba(12, 116, 197, .12);
        }

        .match-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 18px;
            background: #f9fafb;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }

        .match-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #0277bd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .spinner-sm {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php include(__DIR__ . "/inc/sidebar.php"); ?>

    <main class="doctor-content">

        <div style="margin-bottom:20px;">
            <p class="section-title" style="margin:0;">Find Student</p>
            <span style="font-size:.78rem; color:#6b7280;">Search a student by identity, then verify with an email OTP to view their record.</span>
        </div>

        <?php if ($err === 'verify_required'): ?>
            <div class="alert alert-warning" style="max-width:520px;margin:0 auto 18px;font-size:.85rem;">
                <i class="fa fa-lock me-1"></i> Please verify the student's identity before viewing their profile.
            </div>
        <?php endif; ?>

        <!-- Wizard steps -->
        <div class="wizard">
            <div class="wi active" id="si1"><span class="wc">1</span><span class="wl">Search</span></div>
            <div class="wi" id="si2"><span class="wc">2</span><span class="wl">Verify OTP</span></div>
            <div class="wi" id="si3"><span class="wc">3</span><span class="wl">Access</span></div>
        </div>

        <!-- ─── Step 1: Search ─── -->
        <div class="sbody active" id="step1">
            <div class="lookup-card">
                <div class="lookup-title"><i class="fa fa-search me-1"></i> Search By</div>

                <div class="mb-3">
                    <select id="searchType" class="form-control">
                        <option value="phone">Phone Number</option>
                        <option value="aadhar">Aadhaar Number</option>
                        <option value="abha">ABHA Number / Address</option>
                        <option value="email">Email ID</option>
                    </select>
                </div>
                <div class="mb-3">
                    <input type="text" id="searchValue" class="form-control" placeholder="Enter 10-digit mobile number">
                </div>
                <div id="err1" class="alert alert-danger" style="display:none;font-size:.82rem;"></div>

                <button id="btnSearch" class="btn btn-primary w-100 fw-semibold">
                    <i class="fa fa-search me-1"></i> Find Student
                </button>

                <p style="font-size:.74rem;color:#9ca3af;margin:14px 0 0;">
                    <i class="fa fa-shield me-1"></i> For patient privacy, students are not listed. An OTP is sent to the
                    student's registered email to confirm identity before their record is shown.
                </p>
            </div>
        </div>

        <!-- ─── Step 2: OTP ─── -->
        <div class="sbody" id="step2">
            <div class="lookup-card">
                <div class="lookup-title"><i class="fa fa-lock me-1"></i> Verify Identity</div>

                <div class="match-card">
                    <div class="match-avatar" id="matchAvatar">?</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:.92rem;" id="matchName">—</div>
                        <div style="font-size:.76rem;color:#6b7280;" id="matchSchool">—</div>
                        <div style="font-size:.72rem;color:#9ca3af;font-family:monospace;" id="matchUid">—</div>
                    </div>
                </div>

                <p style="font-size:.82rem;color:#6b7280;">
                    An OTP has been sent to <strong id="matchEmail">—</strong>. Ask the student (or their
                    guardian/teacher) for the code to confirm you're accessing the right record.
                </p>

                <div class="otp-row">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" autocomplete="off">
                </div>
                <div id="err2" class="alert alert-danger" style="display:none;font-size:.82rem;"></div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <small><span id="timerEl" style="color:#6b7280;font-size:.78rem;"></span></small>
                    <button id="btnResendOtp" class="btn btn-link btn-sm p-0" style="font-size:.8rem;" disabled>Resend OTP</button>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" onclick="goStep(1)"><i class="fa fa-arrow-left"></i></button>
                    <button id="btnVerifyOtp" class="btn btn-primary flex-fill fw-semibold">
                        <i class="fa fa-check me-1"></i> Verify &amp; Continue
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── Step 3: Success (redirecting) ─── -->
        <div class="sbody" id="step3">
            <div class="lookup-card text-center">
                <i class="fa fa-check-circle" style="font-size:2.4rem;color:#16a34a;"></i>
                <h6 class="fw-bold mt-3">Identity Verified</h6>
                <p style="font-size:.84rem;color:#6b7280;">Opening student profile…</p>
                <div class="spinner-sm" style="border-color:#e5e7eb;border-top-color:#16a34a;"></div>
            </div>
        </div>

    </main>

    <script>
        const BASE = '<?= BASE_URL ?>';

        const placeholders = {
            phone: 'Enter 10-digit mobile number',
            aadhar: 'Enter 12-digit Aadhaar number',
            abha: 'Enter ABHA number or address (name@abdm)',
            email: "Enter student's email address",
        };
        document.getElementById('searchType').addEventListener('change', function() {
            document.getElementById('searchValue').placeholder = placeholders[this.value] || '';
        });

        function goStep(n) {
            ['step1', 'step2', 'step3'].forEach((id, i) =>
                document.getElementById(id).classList.toggle('active', i === n - 1)
            );
            ['si1', 'si2', 'si3'].forEach((id, i) => {
                const el = document.getElementById(id);
                if (i < n - 1) el.className = 'wi done';
                else if (i === n - 1) el.className = 'wi active';
                else el.className = 'wi';
            });
        }

        function showErr(id, msg) {
            const e = document.getElementById(id);
            e.style.display = 'block';
            e.textContent = msg;
        }

        function hideErr(id) {
            document.getElementById(id).style.display = 'none';
        }

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function startTimer(s) {
            document.getElementById('btnResendOtp').disabled = true;
            const el = document.getElementById('timerEl');
            let t = s;
            const iv = setInterval(() => {
                el.textContent = 'Resend in ' + t + 's';
                if (--t < 0) {
                    clearInterval(iv);
                    el.textContent = '';
                    document.getElementById('btnResendOtp').disabled = false;
                }
            }, 1000);
        }

        /* OTP box behaviour */
        document.querySelectorAll('.otp-box').forEach((el, i, all) => {
            el.addEventListener('input', () => {
                el.value = el.value.replace(/\D/g, '').slice(-1);
                if (el.value && all[i + 1]) all[i + 1].focus();
            });
            el.addEventListener('keydown', e => {
                if (e.key === 'Backspace' && !el.value && all[i - 1]) all[i - 1].focus();
            });
            el.addEventListener('paste', e => {
                const d = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                all.forEach((b, j) => {
                    b.value = d[j] || '';
                });
                all[Math.min(5, d.length - 1) || 0].focus();
                e.preventDefault();
            });
        });

        function getOtp() {
            return [...document.querySelectorAll('.otp-box')].map(e => e.value).join('');
        }

        /* ── Step 1: Search ── */
        document.getElementById('btnSearch').addEventListener('click', function() {
            const type = document.getElementById('searchType').value;
            const value = document.getElementById('searchValue').value.trim();
            if (!value) {
                showErr('err1', 'Please enter a value to search.');
                return;
            }
            hideErr('err1');
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-sm me-2" style="border-color:rgba(255,255,255,.4);border-top-color:#fff;"></span>Searching…';

            fetch(BASE + 'doctor/api/school-lookup-search.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type,
                        value
                    })
                })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-search me-1"></i> Find Student';
                    if (!data.success) {
                        showErr('err1', data.error || 'Search failed');
                        return;
                    }
                    document.getElementById('matchAvatar').textContent = (data.name || '?').charAt(0).toUpperCase();
                    document.getElementById('matchName').textContent = data.name;
                    document.getElementById('matchSchool').textContent = data.school_name || '';
                    document.getElementById('matchUid').textContent = data.member_uid || '';
                    document.getElementById('matchEmail').textContent = data.masked_email || '';
                    document.querySelectorAll('.otp-box').forEach(b => b.value = '');
                    hideErr('err2');
                    goStep(2);
                    startTimer(60);
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-search me-1"></i> Find Student';
                    showErr('err1', 'Network error — please retry');
                });
        });

        /* ── Resend OTP ── */
        document.getElementById('btnResendOtp').addEventListener('click', function() {
            if (this.disabled) return;
            this.disabled = true;
            fetch(BASE + 'doctor/api/school-lookup-resend.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: '{}'
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        showErr('err2', data.error || 'Could not resend OTP.');
                        this.disabled = false;
                        return;
                    }
                    hideErr('err2');
                    document.querySelectorAll('.otp-box').forEach(b => b.value = '');
                    startTimer(60);
                })
                .catch(() => {
                    showErr('err2', 'Network error — please retry');
                    this.disabled = false;
                });
        });

        /* ── Step 2: Verify ── */
        document.getElementById('btnVerifyOtp').addEventListener('click', function() {
            const otp = getOtp();
            if (otp.length < 6) {
                showErr('err2', 'Enter the complete 6-digit OTP');
                return;
            }
            hideErr('err2');
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-sm me-1"></span>Verifying…';

            fetch(BASE + 'doctor/api/school-lookup-verify.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        otp
                    })
                })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-check me-1"></i> Verify &amp; Continue';
                    if (!data.success) {
                        showErr('err2', data.error || 'OTP verification failed');
                        return;
                    }
                    goStep(3);
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 900);
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-check me-1"></i> Verify &amp; Continue';
                    showErr('err2', 'Network error — please retry');
                });
        });
    </script>
</body>

</html>

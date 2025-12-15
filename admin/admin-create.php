<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Create Admin User</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .password-strength {
            height: 5px;
            background: #e9ecef;
            margin-top: 5px;
            border-radius: 3px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
    </style>
</head>

<body class="crm_body_bg">
    <?php include "header.php"; ?>
    
    <section class="main_content dashboard_part large_header_bg">
        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>
        
        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Create New Admin User</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="white_card_body">
                                <!-- Display messages -->
                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                                <?php elseif (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                                <?php endif; ?>
                                
                                <form action="" method="POST" class="needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                                            <div class="invalid-feedback">Please enter first name</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                                            <div class="invalid-feedback">Please enter last name</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                        <div class="invalid-feedback">Please enter a valid email</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username *</label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                        <div class="invalid-feedback">Please choose a username</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password * (min 8 characters)</label>
                                        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                                        <div class="password-strength">
                                            <div class="password-strength-bar" id="password-strength-bar"></div>
                                        </div>
                                        <div class="invalid-feedback">Password must be at least 8 characters</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role *</label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="">Select Role</option>
                                            <option value="super_admin">Super Admin</option>
                                            <option value="admin">Admin</option>
                                            <option value="manager">Manager</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a role</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" name="createLogin">Create Admin User</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php include "footer.php"; ?>
    </section>

    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const strengthBar = document.getElementById('password-strength-bar');
            const password = this.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 1;
            // Contains both lower and upper case
            if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 1;
            // Contains numbers
            if (password.match(/([0-9])/)) strength += 1;
            // Contains special chars
            if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) strength += 1;
            
            // Update strength bar
            strengthBar.style.width = (strength * 25) + '%';
            strengthBar.className = 'password-strength-bar ' + 
                (strength < 2 ? 'strength-weak' : 
                 strength < 4 ? 'strength-medium' : 'strength-strong');
        });
        
        // Form validation
        (function() {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
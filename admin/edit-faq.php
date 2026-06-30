<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "db-conn.php";

// Check if ID parameter exists
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid FAQ ID";
    header("Location: manage-faq.php");
    exit();
}

$faq_id = (int)$_GET['id'];

// Fetch FAQ data
$stmt = $conn->prepare("SELECT * FROM faqs WHERE id = ?");
$stmt->bind_param("i", $faq_id);
$stmt->execute();
$result = $stmt->get_result();
$faq = $result->fetch_assoc();

if (!$faq) {
    $_SESSION['error'] = "FAQ not found";
    header("Location: manage-faq.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_faq'])) {
    $page_name = mysqli_real_escape_string($conn, $_POST['page_name']);
    $question = mysqli_real_escape_string($conn, $_POST['question']);
    $answer = mysqli_real_escape_string($conn, $_POST['answer']);
    $status = (int)$_POST['status'];
    
    $update_stmt = $conn->prepare("UPDATE faqs SET page_name = ?, question = ?, answer = ?, status = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("sssii", $page_name, $question, $answer, $status, $faq_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "FAQ updated successfully!";
        header("Location: manage-faq.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating FAQ: " . $update_stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit FAQ | Admin Panel</title>
    <link rel="icon" href="img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --success-color: #4bb543;
        }

        .faq-edit-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .section-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
        }

        .section-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-color);
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(76, 201, 240, 0.25);
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .faq-edit-card {
                padding: 1.5rem;
            }
        }
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
            <div class="container-fluid p-0 ">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="white_card card_height_100 mb_30">
                            <div class="white_card_header">
                                <div class="box_header m-0">
                                    <div class="main-title">
                                        <h2 class="m-0">Edit FAQ</h2>
                                    </div>
                                    <div class="add_button ms-2">
                                        <a href="manage-faq.php" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-arrow-left me-1"></i> Back to FAQs
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="row px-4">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger">
                                    <?= htmlspecialchars($_SESSION['error']) ?>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>
                            </div>

                            <div class="white_card_body p-3">
                                <div class="faq-edit-card">
                                    <h3 class="section-title">Edit FAQ Details</h3>
                                    <form method="POST">
                                        <input type="hidden" name="faq_id" value="<?= $faq_id ?>">

                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="page_name">Page</label>
                                                <select id="page_name" name="page_name" class="form-select" required>
                                                    <option value="home" <?= $faq['page_name'] == 'home' ? 'selected' : '' ?>>Home</option>
                                                    <option value="courses" <?= $faq['page_name'] == 'courses' ? 'selected' : '' ?>>Courses</option>
                                                    <option value="packages" <?= $faq['page_name'] == 'packages' ? 'selected' : '' ?>>Packages</option>
                                                    <option value="general" <?= $faq['page_name'] == 'general' ? 'selected' : '' ?>>General</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="status">Status</label>
                                                <select id="status" name="status" class="form-select" required>
                                                    <option value="1" <?= $faq['status'] ? 'selected' : '' ?>>Active</option>
                                                    <option value="0" <?= !$faq['status'] ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="question">Question</label>
                                            <input type="text" class="form-control" name="question" id="question"
                                                value="<?= htmlspecialchars($faq['question']) ?>"
                                                placeholder="Enter the question" required />
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label" for="answer">Answer</label>
                                            <textarea class="form-control" name="answer" id="answer" rows="6"
                                                placeholder="Enter the detailed answer" required><?= htmlspecialchars($faq['answer']) ?></textarea>
                                        </div>

                                        <div class="d-flex justify-content-between">
                                            <a href="manage-faqs.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times me-1"></i> Cancel
                                            </a>
                                            <button type="submit" class="btn btn-primary" name="update_faq">
                                                <i class="fas fa-save me-1"></i> Update FAQ
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>

    <script>
        // Auto-resize textarea as user types
        document.getElementById('answer').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Initialize textarea height on load
        window.addEventListener('load', function() {
            const textarea = document.getElementById('answer');
            textarea.style.height = (textarea.scrollHeight) + 'px';
        });
    </script>
</body>
</html>
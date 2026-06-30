<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "db-conn.php";

// delete any faq 
if (isset($_GET['id'])) {
    $id = (int) $_GET['id']; // Cast to integer for safety

    $stmt = $conn->prepare("DELETE FROM `faqs` WHERE `id` = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "FAQ deleted successfully!";
    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
    }

}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faq'])) {
    $page_name = mysqli_real_escape_string($conn, $_POST['page_name']);
    $question = mysqli_real_escape_string($conn, $_POST['question']);
    $answer = mysqli_real_escape_string($conn, $_POST['answer']);
    $status = (int)$_POST['status'];
    
    $stmt = $conn->prepare("INSERT INTO faqs (page_name, question, answer, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $page_name, $question, $answer, $status);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "FAQ added successfully!";
    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
    }
    header("Location: manage-faq.php");
    exit();
}

// Fetch existing FAQs
$faqs = [];
$result = $conn->query("SELECT * FROM faqs ORDER BY page_name, created_at DESC");
if ($result) {
    $faqs = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>FAQ Management | Admin Panel</title>
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

        .faq-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .faq-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
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

        .faq-item {
            border-bottom: 1px solid #eee;
            padding: 1.5rem 0;
        }

        .faq-item:last-child {
            border-bottom: none;
        }

        .faq-question {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .faq-answer {
            color: #555;
            margin-bottom: 0.75rem;
        }

        .faq-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .badge-page {
            background-color: var(--accent-color);
            color: white;
        }

        .badge-status-active {
            background-color: var(--success-color);
        }

        .badge-status-inactive {
            background-color: #dc3545;
        }

        @media (max-width: 768px) {
            .faq-card {
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
                                        <h2 class="m-0">FAQ Management</h2>
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

                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success">
                                    <?= htmlspecialchars($_SESSION['success']) ?>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>
                            </div>

                            <div class="white_card_body p-3">
                                <!-- Add FAQ Form -->
                                <div class="faq-card">
                                    <h3 class="section-title">Add New FAQ</h3>
                                    <form method="POST">
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="page_name">Page</label>
                                                <select id="page_name" name="page_name" class="form-select" required>
                                                    <option value="home">Home</option>
                                                    <option value="about">About</option>
                                                    <option value="doctor-profile">Doctor Details</option>
                                                    <option value="packages">Packages</option>
                                                    <option value="general">General</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="status">Status</label>
                                                <select id="status" name="status" class="form-select" required>
                                                    <option value="1" selected>Active</option>
                                                    <option value="0">Inactive</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="question">Question</label>
                                            <input type="text" class="form-control" name="question" id="question"
                                                placeholder="Enter the question" required />
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label" for="answer">Answer</label>
                                            <textarea class="form-control" name="answer" id="answer" rows="4"
                                                placeholder="Enter the detailed answer" required></textarea>
                                        </div>

                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary" name="add_faq">
                                                <i class="fas fa-save me-1"></i> Save FAQ
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Existing FAQs List -->
                                <div class="faq-card">
                                    <h3 class="section-title">Manage Existing FAQs</h3>
                                    
                                    <?php if (empty($faqs)): ?>
                                        <div class="alert alert-info">
                                            No FAQs found. Add your first FAQ above.
                                        </div>
                                    <?php else: ?>
                                        <div class="faq-list">
                                            <?php foreach ($faqs as $faq): ?>
                                                <div class="faq-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h5 class="faq-question"><?= htmlspecialchars($faq['question']) ?></h5>
                                                            <p class="faq-answer"><?= nl2br(htmlspecialchars($faq['answer'])) ?></p>
                                                            <div class="faq-meta">
                                                                <span class="badge badge-page me-2"><?= ucfirst(htmlspecialchars($faq['page_name'])) ?></span>
                                                                <span class="badge <?= $faq['status'] ? 'badge-status-active' : 'badge-status-inactive' ?> me-2">
                                                                    <?= $faq['status'] ? 'Active' : 'Inactive' ?>
                                                                </span>
                                                                <span>Created: <?= date('M d, Y', strtotime($faq['created_at'])) ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="faqActions" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </button>
                                                            <ul class="dropdown-menu" aria-labelledby="faqActions">
                                                                <li><a class="dropdown-item" href="edit-faq.php?id=<?= $faq['id'] ?>"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                                                <li><a class="dropdown-item text-danger" href="?id=<?= $faq['id'] ?>" onclick="return confirm('Are you sure you want to delete this FAQ?')"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
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
    </script>
</body>
</html>
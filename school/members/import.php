<?php
include_once "../../config/connect.php";
include_once "../auth/auth.php";

require_once __DIR__ . '/../../vendor/autoload.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

const MAX_IMPORT_ROWS = 1000;
const MAX_FILE_BYTES = 5 * 1024 * 1024; // 5MB

$HEADER_MAP = [
    'type' => 'type',
    'name' => 'name',
    'full name' => 'name',
    'email' => 'email',
    'email address' => 'email',
    'phone' => 'phone',
    'mobile' => 'phone',
    'mobile number' => 'phone',
    'dob' => 'dob',
    'date of birth' => 'dob',
    'gender' => 'gender',
    'blood group' => 'blood_group',
    'aadhar number' => 'aadhar',
    'aadhar' => 'aadhar',
    'aadhaar number' => 'aadhar',
    'aadhaar' => 'aadhar',
    'address' => 'address',
    'status' => 'status',
    'class' => 'class',
    'section' => 'section',
    'roll number' => 'roll_number',
    'admission number' => 'admission_number',
    'employee id' => 'employee_id',
    'designation' => 'designation',
    'assigned class' => 'assigned_class',
];

function normalize_header(string $h): string
{
    $h = preg_replace('/\(.*?\)/', '', $h); // drop hint text like "(YYYY-MM-DD)"
    $h = strtolower(trim($h));
    $h = preg_replace('/[^a-z0-9]+/', ' ', $h);
    return trim($h);
}

function cell(array $row, array $colMap, string $key): string
{
    return isset($colMap[$key], $row[$colMap[$key]]) ? trim((string) $row[$colMap[$key]]) : '';
}

/** @return array{0: array, 1: array[]} [header row, data rows] */
function parse_uploaded_file(string $tmpPath, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        $fh = fopen($tmpPath, 'r');
        if (!$fh) throw new RuntimeException('Could not read the uploaded file.');

        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);
            throw new RuntimeException('The file appears to be empty.');
        }
        // Strip a UTF-8 BOM if Excel added one to the first header cell.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $rows = [];
        while (($data = fgetcsv($fh)) !== false) {
            if (count(array_filter($data, fn($v) => trim((string) $v) !== '')) === 0) continue;
            $rows[] = $data;
        }
        fclose($fh);
        return [$header, $rows];
    }

    if (in_array($ext, ['xlsx', 'xls'], true)) {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
        $data = $spreadsheet->getSheet(0)->toArray(null, true, true, false);
        if (empty($data)) throw new RuntimeException('The file appears to be empty.');

        $header = array_map(fn($v) => (string) $v, array_shift($data));
        $rows = [];
        foreach ($data as $row) {
            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) continue;
            $rows[] = $row;
        }
        return [$header, $rows];
    }

    throw new RuntimeException('Unsupported file type. Please upload a .csv or .xlsx file (use the template).');
}

function build_preview(array $header, array $rows, mysqli $conn, int $school_id, array $HEADER_MAP): array
{
    $colMap = [];
    foreach ($header as $i => $h) {
        $norm = normalize_header((string) $h);
        if (isset($HEADER_MAP[$norm])) {
            $colMap[$HEADER_MAP[$norm]] = $i;
        }
    }
    if (!isset($colMap['type']) || !isset($colMap['name'])) {
        throw new RuntimeException('The file must include "Type" and "Name" columns. Please use the provided template.');
    }
    if (count($rows) > MAX_IMPORT_ROWS) {
        $n = count($rows);
        throw new RuntimeException("This file has $n data rows — the limit per import is " . MAX_IMPORT_ROWS . ". Please split it into smaller files.");
    }

    // Pull existing unique-key values for this school once, instead of querying per row.
    $existing_emails = $existing_rolls = $existing_emp_ids = [];
    $stmt = $conn->prepare("SELECT email, roll_number, employee_id FROM school_members WHERE school_id = ?");
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['email'])) $existing_emails[strtolower($row['email'])] = true;
        if (!empty($row['roll_number'])) $existing_rolls[strtolower($row['roll_number'])] = true;
        if (!empty($row['employee_id'])) $existing_emp_ids[strtolower($row['employee_id'])] = true;
    }

    $seen_emails = $seen_rolls = $seen_emp = [];
    $preview = [];

    foreach ($rows as $idx => $row) {
        $line = $idx + 2; // +1 for header, +1 for 1-indexing
        $errors = [];

        $type_raw = cell($row, $colMap, 'type');
        $type = ucfirst(strtolower($type_raw));
        if (!in_array($type, ['Student', 'Teacher', 'Staff'], true)) {
            $errors[] = "Type must be Student, Teacher, or Staff (got \"$type_raw\")";
        }

        $name = cell($row, $colMap, 'name');
        if ($name === '') $errors[] = 'Name is required';

        $email = cell($row, $colMap, 'email');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is not a valid address';
        } elseif ($email !== '') {
            $key = strtolower($email);
            if (isset($existing_emails[$key])) $errors[] = 'Email already exists in your school records';
            elseif (isset($seen_emails[$key])) $errors[] = "Duplicate email within this file (row {$seen_emails[$key]})";
        }

        $aadhar = cell($row, $colMap, 'aadhar');
        if ($aadhar !== '' && !preg_match('/^\d{12}$/', $aadhar)) {
            $errors[] = 'Aadhar number must be exactly 12 digits';
        }

        $dob_raw = cell($row, $colMap, 'dob');
        $dob = null;
        if ($dob_raw !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $dob_raw);
            if ($d && $d->format('Y-m-d') === $dob_raw) {
                $dob = $dob_raw;
            } else {
                $errors[] = 'DOB must be in YYYY-MM-DD format';
            }
        }

        $gender_raw = cell($row, $colMap, 'gender');
        $gender = '';
        if ($gender_raw !== '') {
            $g = ucfirst(strtolower($gender_raw));
            if (in_array($g, ['Male', 'Female', 'Other'], true)) $gender = $g;
            else $errors[] = 'Gender must be Male, Female, or Other';
        }

        $blood_raw = cell($row, $colMap, 'blood_group');
        $blood = '';
        if ($blood_raw !== '') {
            $b = strtoupper(str_replace(' ', '', $blood_raw));
            if (in_array($b, ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'], true)) $blood = $b;
            else $errors[] = 'Blood group must be one of A+, A-, B+, B-, O+, O-, AB+, AB-';
        }

        $status_raw = cell($row, $colMap, 'status');
        $status = 'Active';
        if ($status_raw !== '') {
            $s = ucfirst(strtolower($status_raw));
            if (in_array($s, ['Active', 'Inactive'], true)) $status = $s;
            else $errors[] = 'Status must be Active or Inactive';
        }

        $roll_number = cell($row, $colMap, 'roll_number');
        if ($type === 'Student' && $roll_number !== '') {
            $key = strtolower($roll_number);
            if (isset($existing_rolls[$key])) $errors[] = 'Roll number already exists in your school records';
            elseif (isset($seen_rolls[$key])) $errors[] = "Duplicate roll number within this file (row {$seen_rolls[$key]})";
        }

        $employee_id = cell($row, $colMap, 'employee_id');
        if (in_array($type, ['Teacher', 'Staff'], true) && $employee_id !== '') {
            $key = strtolower($employee_id);
            if (isset($existing_emp_ids[$key])) $errors[] = 'Employee ID already exists in your school records';
            elseif (isset($seen_emp[$key])) $errors[] = "Duplicate Employee ID within this file (row {$seen_emp[$key]})";
        }

        $entry = [
            'line' => $line,
            'type' => $type,
            'name' => $name,
            'email' => $email,
            'phone' => cell($row, $colMap, 'phone'),
            'dob' => $dob,
            'gender' => $gender,
            'blood_group' => $blood,
            'aadhar' => $aadhar,
            'address' => cell($row, $colMap, 'address'),
            'status' => $status,
            'class' => cell($row, $colMap, 'class'),
            'section' => cell($row, $colMap, 'section'),
            'roll_number' => $roll_number,
            'admission_number' => cell($row, $colMap, 'admission_number'),
            'employee_id' => $employee_id,
            'designation' => cell($row, $colMap, 'designation'),
            'assigned_class' => cell($row, $colMap, 'assigned_class'),
            'errors' => $errors,
        ];

        if (empty($errors)) {
            if ($email !== '') $seen_emails[strtolower($email)] = $line;
            if ($type === 'Student' && $roll_number !== '') $seen_rolls[strtolower($roll_number)] = $line;
            if (in_array($type, ['Teacher', 'Staff'], true) && $employee_id !== '') $seen_emp[strtolower($employee_id)] = $line;
        }

        $preview[] = $entry;
    }

    return $preview;
}

$page_error = '';
$stage = $_GET['stage'] ?? 'upload';

// ---- Step 1: handle the file upload, parse + validate, stash in session, redirect (PRG) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_upload'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_error = 'Security check failed. Please try again.';
    } elseif (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $page_error = 'Please choose a .csv or .xlsx file to upload.';
    } elseif ($_FILES['import_file']['size'] > MAX_FILE_BYTES) {
        $page_error = 'File is too large (max 5 MB).';
    } else {
        try {
            [$header, $rows] = parse_uploaded_file($_FILES['import_file']['tmp_name'], $_FILES['import_file']['name']);
            if (empty($rows)) {
                $page_error = 'No data rows found in the file (only a header row, or it\'s empty).';
            } else {
                $preview = build_preview($header, $rows, $conn, $school_id, $HEADER_MAP);
                $_SESSION['import_preview'] = $preview;
                header('Location: import.php?stage=preview');
                exit();
            }
        } catch (Throwable $e) {
            $page_error = $e->getMessage();
        }
    }
}

// ---- Step 2: confirm — insert only the valid rows, in a transaction ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_confirm'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_error = 'Security check failed. Please try again.';
        $stage = 'preview';
    } elseif (empty($_SESSION['import_preview'])) {
        header('Location: import.php');
        exit();
    } else {
        $valid_rows = array_values(array_filter($_SESSION['import_preview'], fn($r) => empty($r['errors'])));
        $inserted = 0;
        $failed = [];

        $conn->begin_transaction();
        $stmt = $conn->prepare("
            INSERT INTO school_members
                (school_id, type, name, email, phone, dob, gender, blood_group, aadhar_number, address, status,
                 class, section, roll_number, admission_number, employee_id, designation, assigned_class, added_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        foreach ($valid_rows as $row) {
            $email = $row['email'] !== '' ? $row['email'] : null;
            $phone = $row['phone'] !== '' ? $row['phone'] : null;
            $gender = $row['gender'] !== '' ? $row['gender'] : null;
            $blood = $row['blood_group'] !== '' ? $row['blood_group'] : null;
            $aadhar = $row['aadhar'] !== '' ? $row['aadhar'] : null;
            $address = $row['address'] !== '' ? $row['address'] : null;
            $class = $row['class'] !== '' ? $row['class'] : null;
            $section = $row['section'] !== '' ? $row['section'] : null;
            $roll = $row['roll_number'] !== '' ? $row['roll_number'] : null;
            $admission = $row['admission_number'] !== '' ? $row['admission_number'] : null;
            $emp = $row['employee_id'] !== '' ? $row['employee_id'] : null;
            $desig = $row['designation'] !== '' ? $row['designation'] : null;
            $assigned = $row['assigned_class'] !== '' ? $row['assigned_class'] : null;

            $params = [
                $school_id, $row['type'], $row['name'], $email, $phone, $row['dob'], $gender, $blood,
                $aadhar, $address, $row['status'], $class, $section, $roll, $admission, $emp, $desig,
                $assigned, $school_user_id,
            ];
            $types = 'i' . str_repeat('s', 17) . 'i';
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $inserted++;
            } else {
                $failed[] = "Row {$row['line']} ({$row['name']}): " . $conn->error;
            }
        }
        $conn->commit();

        unset($_SESSION['import_preview']);
        $_SESSION['import_result'] = ['inserted' => $inserted, 'failed' => $failed];
        header('Location: import.php?stage=done');
        exit();
    }
}

// ---- Cancel: discard the pending preview ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_cancel'])) {
    unset($_SESSION['import_preview']);
    header('Location: import.php');
    exit();
}

if ($stage === 'preview' && empty($_SESSION['import_preview'])) {
    $stage = 'upload';
}
if ($stage === 'done' && empty($_SESSION['import_result'])) {
    $stage = 'upload';
}

$preview = $_SESSION['import_preview'] ?? [];
$ready_count = count(array_filter($preview, fn($r) => empty($r['errors'])));
$error_count = count($preview) - $ready_count;

$result = $_SESSION['import_result'] ?? null;
if ($stage === 'done') unset($_SESSION['import_result']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | Bulk Import Members</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
  <style>
    .sec-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 8px rgba(0, 0, 0, .06);
      margin-bottom: 20px;
      overflow: hidden;
    }

    .sec-card-head {
      padding: 14px 20px;
      border-bottom: 1px solid #f3f4f6;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .sec-card-head .icon {
      width: 34px;
      height: 34px;
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .88rem;
      flex-shrink: 0;
      background: #eaf4fd;
      color: var(--primary);
    }

    .sec-card-head h6 {
      margin: 0;
      font-size: .9rem;
      font-weight: 700;
      color: #1f2937;
    }

    .sec-card-head p {
      margin: 0;
      font-size: .73rem;
      color: #9ca3af;
    }

    .sec-card-body {
      padding: 20px;
    }

    .upload-drop {
      border: 2px dashed #d1d5db;
      border-radius: 12px;
      padding: 40px 20px;
      text-align: center;
      background: #f9fafb;
      transition: .2s;
      cursor: pointer;
    }

    .upload-drop:hover,
    .upload-drop.dragover {
      border-color: var(--primary);
      background: #eaf4fd;
    }

    .row-error {
      background: #fef2f2 !important;
    }

    .row-ok {
      background: #f0fdf4 !important;
    }

    .err-list {
      font-size: .74rem;
      color: #dc2626;
      margin: 0;
      padding-left: 16px;
    }

    .stat-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 20px;
      font-weight: 700;
      font-size: .84rem;
    }
  </style>
</head>

<body>
  <?php $active_page = 'import';
  $base_path = '../';
  include '../inc/sidebar-school.php'; ?>

  <div class="school-topbar">
    <div class="d-flex align-items-center gap-2">
      <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;">
        <i class="fas fa-file-import me-2 text-primary"></i>Bulk Import Members
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="list.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i><span
          class="d-none d-sm-inline">Back to List</span></a>
    </div>
  </div>

  <main class="school-content">

    <?php if ($page_error): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($page_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if ($stage === 'upload'): ?>

      <div class="sec-card">
        <div class="sec-card-head">
          <div class="icon"><i class="fas fa-download"></i></div>
          <div>
            <h6>Step 1 — Download the Template</h6>
            <p>Fill this in with your students, teachers, and staff</p>
          </div>
        </div>
        <div class="sec-card-body">
          <p class="mb-3" style="font-size:.86rem;color:#4b5563;">
            The template has an <strong>Instructions</strong> tab explaining every column, plus a dropdown for
            the <strong>Type</strong> column so it's hard to get wrong. Only <strong>Type</strong> and
            <strong>Name</strong> are required — everything else is optional.
          </p>
          <a href="import-template.php" class="btn btn-primary">
            <i class="fas fa-file-excel me-2"></i>Download Excel Template
          </a>
        </div>
      </div>

      <div class="sec-card">
        <div class="sec-card-head">
          <div class="icon"><i class="fas fa-upload"></i></div>
          <div>
            <h6>Step 2 — Upload Your Filled-In File</h6>
            <p>.csv or .xlsx, up to 1000 members, max 5 MB</p>
          </div>
        </div>
        <div class="sec-card-body">
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <label for="import_file" class="upload-drop d-block" id="dropZone">
              <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
              <div style="font-weight:600;color:#1f2937;" id="dropLabel">Click to choose a file, or drag it here</div>
              <div style="font-size:.78rem;color:#9ca3af;margin-top:4px;">CSV or XLSX only</div>
            </label>
            <input type="file" id="import_file" name="import_file" accept=".csv,.xlsx,.xls" class="d-none" required>
            <div class="mt-3 text-end">
              <button type="submit" name="do_upload" value="1" class="btn btn-success px-4">
                <i class="fas fa-check me-2"></i>Upload &amp; Preview
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="alert alert-info" style="font-size:.84rem;">
        <i class="fas fa-info-circle me-2"></i>
        Nothing is saved yet at this stage — you'll see a full preview with any errors highlighted before anything
        is added to your school's records. Passwords and photos can't be bulk-imported; set those per member
        afterward from the members list.
      </div>

    <?php elseif ($stage === 'preview'): ?>

      <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="stat-pill" style="background:#d1fae5;color:#065f46;">
          <i class="fas fa-check-circle"></i> <?= $ready_count ?> ready to import
        </span>
        <?php if ($error_count > 0): ?>
          <span class="stat-pill" style="background:#fee2e2;color:#991b1b;">
            <i class="fas fa-exclamation-circle"></i> <?= $error_count ?> with errors (will be skipped)
          </span>
        <?php endif; ?>
        <span class="stat-pill" style="background:#eef2ff;color:#3730a3;">
          <i class="fas fa-list"></i> <?= count($preview) ?> total rows
        </span>
      </div>

      <div class="sec-card">
        <div class="sec-card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th width="50">Row</th>
                  <th width="90">Type</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Class/Emp ID</th>
                  <th width="220">Result</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($preview as $row): ?>
                  <tr class="<?= empty($row['errors']) ? 'row-ok' : 'row-error' ?>">
                    <td><?= (int) $row['line'] ?></td>
                    <td><?= htmlspecialchars($row['type']) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td>
                      <?= htmlspecialchars($row['type'] === 'Student' ? $row['roll_number'] : $row['employee_id']) ?>
                    </td>
                    <td>
                      <?php if (empty($row['errors'])): ?>
                        <span class="text-success"><i class="fas fa-check me-1"></i>Ready</span>
                      <?php else: ?>
                        <ul class="err-list">
                          <?php foreach ($row['errors'] as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                          <?php endforeach; ?>
                        </ul>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="sec-card">
        <div class="sec-card-body d-flex flex-wrap gap-2 align-items-center">
          <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <button type="submit" name="do_confirm" value="1" class="btn btn-success px-4" <?= $ready_count === 0 ? 'disabled' : '' ?>>
              <i class="fas fa-check-double me-2"></i>Confirm &amp; Import <?= $ready_count ?> Member<?= $ready_count === 1 ? '' : 's' ?>
            </button>
          </form>
          <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <button type="submit" name="do_cancel" value="1" class="btn btn-outline-secondary px-3">
              <i class="fas fa-times me-2"></i>Cancel &amp; Start Over
            </button>
          </form>
          <?php if ($error_count > 0): ?>
            <span class="text-muted ms-auto" style="font-size:.82rem;">
              Rows with errors will be skipped. Fix them in your file and re-upload just those rows if needed.
            </span>
          <?php endif; ?>
        </div>
      </div>

    <?php elseif ($stage === 'done'): ?>

      <div class="sec-card">
        <div class="sec-card-body text-center py-5">
          <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
          <h5 class="fw-bold"><?= (int) $result['inserted'] ?> member<?= $result['inserted'] === 1 ? '' : 's' ?> imported successfully</h5>

          <?php if (!empty($result['failed'])): ?>
            <div class="alert alert-warning text-start mt-4" style="font-size:.84rem;">
              <strong><?= count($result['failed']) ?> row(s) failed at save time:</strong>
              <ul class="mb-0 mt-2">
                <?php foreach ($result['failed'] as $f): ?>
                  <li><?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <div class="mt-4 d-flex justify-content-center gap-2">
            <a href="list.php" class="btn btn-primary px-4"><i class="fas fa-users me-2"></i>View Members List</a>
            <a href="import.php" class="btn btn-outline-secondary px-3"><i class="fas fa-file-import me-2"></i>Import More</a>
          </div>
        </div>
      </div>

    <?php endif; ?>

  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const fileInput = document.getElementById('import_file');
    const dropZone = document.getElementById('dropZone');
    const dropLabel = document.getElementById('dropLabel');
    if (fileInput && dropZone) {
      fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) dropLabel.textContent = fileInput.files[0].name;
      });
      ['dragover', 'dragenter'].forEach(evt => dropZone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
      }));
      ['dragleave', 'drop'].forEach(evt => dropZone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
      }));
      dropZone.addEventListener('drop', (e) => {
        if (e.dataTransfer.files[0]) {
          fileInput.files = e.dataTransfer.files;
          dropLabel.textContent = e.dataTransfer.files[0].name;
        }
      });
    }
  </script>
</body>

</html>

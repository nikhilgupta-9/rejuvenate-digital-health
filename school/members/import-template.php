<?php
include_once "../../config/connect.php";
include_once "../auth/auth.php";

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$columns = [
    'Type' => 20,            // Student / Teacher / Staff (required)
    'Name' => 24,             // required
    'Email' => 26,
    'Phone' => 14,
    'DOB (YYYY-MM-DD)' => 16,
    'Gender' => 12,           // Male / Female / Other
    'Blood Group' => 12,      // A+ A- B+ B- O+ O- AB+ AB-
    'Aadhar Number' => 16,    // 12 digits, optional
    'Address' => 30,
    'Status' => 12,           // Active / Inactive (defaults to Active)
    'Class' => 10,            // Students only
    'Section' => 10,          // Students only
    'Roll Number' => 14,      // Students only
    'Admission Number' => 16, // Students only
    'Employee ID' => 14,      // Teacher/Staff only
    'Designation' => 20,      // Teacher/Staff only
    'Assigned Class' => 16,   // Teacher only
];
$headers = array_keys($columns);

$exampleRows = [
    ['Student', 'Aarav Sharma', 'aarav.sharma@example.com', '9876543210', '2012-05-14', 'Male', 'B+', '', 'House 12, Sector 4', 'Active', '8', 'A', '23', 'ADM-2026-023', '', '', ''],
    ['Teacher', 'Priya Verma', 'priya.verma@example.com', '9876500000', '1990-08-02', 'Female', 'O+', '', '', 'Active', '', '', '', '', 'EMP-045', 'Mathematics Teacher', 'Class 8-A'],
];

$spreadsheet = new Spreadsheet();

// --- Sheet 1: the data schools actually fill in ---
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Import Data');

foreach ($headers as $i => $h) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . '1', $h);
    $sheet->getColumnDimension($col)->setWidth($columns[$h]);
}
$headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
$sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0C74C5');
$sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->freezePane('A2');

foreach ($exampleRows as $r => $row) {
    foreach ($row as $i => $val) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValueExplicit($col . ($r + 2), $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    }
}
$exampleRange = 'A2:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . (count($exampleRows) + 1);
$sheet->getStyle($exampleRange)->getFont()->setItalic(true)->getColor()->setRGB('9CA3AF');

// Dropdown validation on Type — the one field the importer can't guess.
$typeColRange = 'A2:A1000';
$validation = $sheet->getCell('A2')->getDataValidation();
$validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
$validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
$validation->setAllowBlank(true);
$validation->setShowDropDown(true);
$validation->setFormula1('"Student,Teacher,Staff"');
for ($row = 2; $row <= 1000; $row++) {
    $sheet->getCell('A' . $row)->setDataValidation(clone $validation);
}

// --- Sheet 2: plain-language instructions ---
$help = $spreadsheet->createSheet();
$help->setTitle('Instructions');
$help->getColumnDimension('A')->setWidth(95);
$lines = [
    'How to use this template',
    '',
    '1. Fill in one row per student, teacher, or staff member on the "Import Data" tab.',
    '2. Delete the two example rows (Aarav Sharma / Priya Verma) before uploading — they are just for reference.',
    '3. "Type" must be exactly Student, Teacher, or Staff — use the dropdown in that column.',
    '4. "Name" and "Type" are the only required fields; everything else can be left blank.',
    '5. "DOB" must be in YYYY-MM-DD format (e.g. 2012-05-14), or left blank.',
    '6. "Email", "Roll Number", and "Employee ID" must be unique — duplicates (in this file or already in your school\'s records) will be skipped and reported.',
    '7. "Class", "Section", "Roll Number", "Admission Number" apply to Students.',
    '8. "Employee ID", "Designation", "Assigned Class" apply to Teachers/Staff.',
    '9. Passwords and photos cannot be bulk-imported — set those individually per member after import, from the members list.',
    '10. Save this file and upload it on the Bulk Import page. You will see a preview with any errors before anything is saved.',
];
foreach ($lines as $i => $line) {
    $help->setCellValue('A' . ($i + 1), $line);
}
$help->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="member-import-template.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();

<?php
/**
 * Public AJAX endpoint — GET ?department=<slug>
 * Returns active doctors practising in the given department.
 */
include_once __DIR__ . '/../config/connect.php';
include_once __DIR__ . '/function.php';

header('Content-Type: application/json');

$slug = trim($_GET['department'] ?? '');
if ($slug === '') {
    echo json_encode(['success' => false, 'message' => 'Department is required', 'doctors' => []]);
    exit;
}

$doctors = get_doctors_by_department_slug($slug);

$out = array_map(function ($d) {
    return [
        'id'                => (int) $d['id'],
        'name'              => $d['name'],
        'slug_url'          => $d['slug_url'],
        'degrees'           => $d['degrees'],
        'specialization'    => $d['specialization'],
        'experience_years'  => $d['experience_years'],
        'rating'            => $d['rating'],
        'languages'         => $d['languages'],
        'profile_image'     => $d['profile_image'] ? BASE_URL . 'admin/' . $d['profile_image'] : null,
        'consultation_fee'  => $d['consultation_fee'],
        'hpr_verified'      => (bool) $d['hpr_verified'],
    ];
}, $doctors);

echo json_encode(['success' => true, 'doctors' => $out]);

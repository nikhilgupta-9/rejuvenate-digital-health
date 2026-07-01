<?php
/**
 * API: Search ABHA by address or 14-digit number
 * GET ?type=address&q=name@abdm
 * GET ?type=number&q=12-3456-7890-1234
 * Returns { found, profile:{name,gender,dob,abhaNumber,abhaAddress,mobile,state,district} }
 */
require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';
require_once dirname(dirname(__DIR__)) . '/config/abdm.php';
require_once dirname(dirname(__DIR__)) . '/lib/AbdmApi.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

$type = trim($_GET['type'] ?? '');
$q    = trim($_GET['q']    ?? '');

if (!$q) { echo json_encode(['success'=>false,'error'=>'Query is required']); exit; }

if (!ABDM_CONFIGURED) {
    echo json_encode(['success'=>false,'error'=>'ABDM not configured on this server']);
    exit;
}

try {
    $api = new AbdmApi();

    if ($type === 'number') {
        $digits = preg_replace('/\D/', '', $q);
        if (strlen($digits) !== 14) {
            echo json_encode(['success'=>false,'error'=>'ABHA number must be 14 digits']);
            exit;
        }
        $res = $api->searchByHealthId(AbdmApi::formatAbhaNumber($digits));
    } else {
        // Default: address search
        $addr = strpos($q,'@') !== false ? $q : $q.'@abdm';
        $res  = $api->searchByAbhaAddress($addr);
    }

    if (!AbdmApi::wasSuccessful($res) || empty($res['ABHANumber'])) {
        echo json_encode(['success'=>false,'found'=>false,'message'=>AbdmApi::extractError($res,'ABHA not found')]);
        exit;
    }

    $profile = [
        'abha_number'  => AbdmApi::formatAbhaNumber($res['ABHANumber'] ?? ''),
        'abha_address' => $res['preferredAbhaAddress'] ?? ($res['ABHAAddress'] ?? ''),
        'name'         => trim(($res['firstName']??'').' '.($res['middleName']??'').' '.($res['lastName']??'')),
        'first_name'   => $res['firstName']  ?? '',
        'middle_name'  => $res['middleName'] ?? '',
        'last_name'    => $res['lastName']   ?? '',
        'gender'       => $res['gender']     ?? '',
        'dob'          => $res['dob']        ?? ($res['birthdate'] ?? ''),
        'mobile'       => $res['mobile']     ?? '',
        'email'        => $res['email']      ?? '',
        'state_name'   => $res['stateName']  ?? ($res['state'] ?? ''),
        'district'     => $res['districtName'] ?? ($res['district'] ?? ''),
        'pincode'      => $res['pinCode']    ?? '',
        'photo'        => $res['photo']      ?? '',
    ];

    echo json_encode(['success'=>true,'found'=>true,'profile'=>$profile]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

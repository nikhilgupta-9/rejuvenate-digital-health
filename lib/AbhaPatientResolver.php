<?php
/**
 * AbhaPatientResolver — turns an ABDM profile into a `users` row.
 *
 * Implements the M1 "New vs Returning Patient" requirement as one shared
 * implementation instead of four near-identical copies that used to live
 * in doctor/api/abdm_save_patient.php, abdm_set_address.php,
 * abha-otp-verify.php, and abha-select-user.php.
 *
 * Match order: abha_number -> mobile -> email (first hit wins).
 */
require_once __DIR__ . '/Abha.php';

class AbhaPatientResolver
{
    /**
     * Normalise an ABDM profile response into a flat field set.
     * Accepts either the full /profile/account response or the ABHAProfile
     * block returned by enrollment/enrol/byAadhaar — both use the same
     * ABDM field names, just at different nesting.
     */
    public static function normalizeAbdmProfile(array $p): array
    {
        $name = trim($p['name'] ?? '');
        if ($name === '') {
            $first  = trim($p['firstName']  ?? '');
            $middle = trim($p['middleName'] ?? '');
            $last   = trim($p['lastName']   ?? '');
            $name   = trim(preg_replace('/\s+/', ' ', "$first $middle $last"));
        }

        $abhaAddress = $p['preferredAbhaAddress'] ?? ($p['ABHAAddress'] ?? ($p['healthId'] ?? ''));
        if (!$abhaAddress && !empty($p['phrAddress'][0])) {
            $abhaAddress = $p['phrAddress'][0];
        }

        $gender = strtoupper(trim($p['gender'] ?? ''));
        if ($gender === 'M') $gender = 'Male';
        elseif ($gender === 'F') $gender = 'Female';
        elseif ($gender !== 'Male' && $gender !== 'Female') $gender = '';

        $dobRaw = $p['dob'] ?? ($p['birthdate'] ?? ($p['dateOfBirth'] ?? ''));
        $dobDb  = null;
        if ($dobRaw) {
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dobRaw, $m)) {
                $dobDb = "$m[3]-$m[2]-$m[1]";
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobRaw)) {
                $dobDb = $dobRaw;
            } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dobRaw, $m)) {
                $dobDb = "$m[3]-$m[2]-$m[1]";
            }
        }

        return [
            'abha_number'  => AbdmApi::formatAbhaNumber($p['ABHANumber'] ?? ($p['abhaNumber'] ?? '')),
            'abha_address' => $abhaAddress,
            'name'         => $name,
            'mobile'       => preg_replace('/\D/', '', $p['mobile'] ?? ''),
            'email'        => trim($p['email'] ?? ''),
            'gender'       => $gender,
            'dob'          => $dobDb,
            'dob_raw'      => $dobRaw,
            'address'      => $p['address'] ?? '',
            'state'        => $p['stateName']    ?? ($p['state']    ?? ''),
            'district'     => $p['districtName'] ?? ($p['district'] ?? ''),
            'pincode'      => $p['pinCode']      ?? '',
            'photo'        => $p['profilePhoto'] ?? ($p['photo'] ?? ''),
        ];
    }

    /**
     * Find-or-create the `users` row for a normalised ABDM profile and
     * link it to the doctor who fetched it.
     *
     * @param array $profile Output of normalizeAbdmProfile().
     * @return array{patient_id:int, is_new:bool}
     * @throws RuntimeException if no existing patient matches and the
     *         profile has no mobile number to register a new one with.
     */
    public static function resolveFromProfile(mysqli $conn, array $profile, int $doctorId): array
    {
        $abhaNumber = $profile['abha_number'] ?? '';
        $mobile     = $profile['mobile']      ?? '';
        $email      = $profile['email']       ?? '';

        $existing = null;

        if ($abhaNumber) {
            $hit = Abha::find($conn, $abhaNumber);
            if ($hit && $hit['entity_type'] === 'patient') {
                $existing = ['id' => (int) $hit['entity_id']];
            }
        }
        if (!$existing && $mobile) {
            $s = $conn->prepare("SELECT id FROM users WHERE mobile=? LIMIT 1");
            $s->bind_param('s', $mobile);
            $s->execute();
            $existing = $s->get_result()->fetch_assoc();
            $s->close();
        }
        if (!$existing && $email) {
            $s = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $s->bind_param('s', $email);
            $s->execute();
            $existing = $s->get_result()->fetch_assoc();
            $s->close();
        }

        $isNew = false;

        if ($existing) {
            $patientId = (int)$existing['id'];
            $upd = $conn->prepare("
                UPDATE users SET
                  name          = CASE WHEN name='' OR name IS NULL THEN ? ELSE name END,
                  gender        = CASE WHEN gender='' OR gender IS NULL THEN ? ELSE gender END,
                  dob           = CASE WHEN dob IS NULL THEN ? ELSE dob END
                WHERE id = ?
            ");
            $upd->bind_param(
                'sssi',
                $profile['name'],
                $profile['gender'],
                $profile['dob'],
                $patientId
            );
            $upd->execute();
            $upd->close();

            // ABHA identity -> abha_accounts (authoritative; mirrors legacy cols)
            Abha::save($conn, 'patient', $patientId, [
                'abha_number'  => $abhaNumber,
                'abha_address' => $profile['abha_address'],
                'linked'       => 1,
                'verified'     => 1,
                'source'       => 'abdm',
            ]);
        } else {
            $isNew = true;
            if (!$mobile) {
                throw new RuntimeException('ABDM profile has no mobile number — cannot register a new patient.');
            }

            $tempPass = bin2hex(random_bytes(8));
            $hash     = password_hash($tempPass, PASSWORD_BCRYPT, ['cost' => 12]);

            $ins = $conn->prepare("
                INSERT INTO users
                  (name, email, mobile, password, gender, dob,
                   zip_code, city, state, address, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
            ");
            $ins->bind_param(
                'ssssssssss',
                $profile['name'],
                $email,
                $mobile,
                $hash,
                $profile['gender'],
                $profile['dob'],
                $profile['pincode'],
                $profile['district'],
                $profile['state'],
                $profile['address']
            );
            if (!$ins->execute()) {
                throw new RuntimeException('Failed to create patient record: ' . $conn->error);
            }
            $patientId = (int)$conn->insert_id;
            $ins->close();

            // ABHA identity -> abha_accounts (authoritative; mirrors legacy cols)
            Abha::save($conn, 'patient', $patientId, [
                'abha_number'  => $abhaNumber,
                'abha_address' => $profile['abha_address'],
                'linked'       => 1,
                'verified'     => 1,
                'source'       => 'abdm',
            ]);
        }

        self::linkToDoctor($conn, $doctorId, $patientId);

        return ['patient_id' => $patientId, 'is_new' => $isNew];
    }

    /** Link doctor <-> patient. doctor_patients schema: database/migration_doctor_abha.sql */
    private static function linkToDoctor(mysqli $conn, int $doctorId, int $patientId): void
    {
        $lnk = $conn->prepare("
            INSERT INTO doctor_patients (doctor_id, patient_id, added_via, abha_fetched)
            VALUES (?, ?, 'abha', 1)
            ON DUPLICATE KEY UPDATE added_via='abha', abha_fetched=1
        ");
        $lnk->bind_param('ii', $doctorId, $patientId);
        $lnk->execute();
        $lnk->close();
    }
}

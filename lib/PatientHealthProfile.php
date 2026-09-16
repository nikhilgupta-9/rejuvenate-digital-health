<?php
/**
 * PatientHealthProfile — single access point for `patient_health_profiles`.
 *
 * One row per patient (users.id), edited from three places — admin
 * (edit-customer.php), doctor (patient-details.php) and the patient
 * themselves (user/health-profile.php) — so the read/upsert + BMI calc
 * lives here once instead of three times. See
 * database/migration_patient_health_profiles.sql.
 */
class PatientHealthProfile
{
    /** Every writable field, keyed by name => bind type ('s' string, 'd' double, 'i' int). */
    public const FIELDS = [
        'height_cm'                  => 'd',
        'weight_kg'                  => 'd',
        'blood_group'                => 's',
        'blood_pressure'             => 's',
        'pulse_rate'                 => 'i',
        'vision_left'                => 's',
        'vision_right'               => 's',
        'known_allergies'            => 's',
        'chronic_conditions'         => 's',
        'current_medications'        => 's',
        'past_surgeries'             => 's',
        'disability'                 => 's',
        'vaccination_details'        => 's',
        'is_vaccinated'              => 'i',
        'emergency_contact_name'     => 's',
        'emergency_contact_phone'    => 's',
        'emergency_contact_relation' => 's',
        'last_checkup_date'          => 's',
        'next_checkup_date'          => 's',
        'checkup_notes'              => 's',
        'insurance_provider'         => 's',
        'insurance_number'           => 's',
    ];

    /** The profile row for a patient, or null if none saved yet. */
    public static function get(mysqli $conn, int $patientId): ?array
    {
        $st = $conn->prepare("SELECT * FROM patient_health_profiles WHERE patient_id = ? LIMIT 1");
        $st->bind_param('i', $patientId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        return $row;
    }

    /**
     * Upsert the profile. $f may contain any subset of self::FIELDS — missing
     * keys keep their current stored value (partial update, same merge
     * pattern as Abha::save()). Height+weight together auto-recompute BMI.
     *
     * @param string $role 'patient' | 'doctor' | 'admin'
     * @param int    $updatedBy id of the editor (users.id / doctors.id / admin_user.id)
     */
    public static function save(mysqli $conn, int $patientId, array $f, string $role, int $updatedBy): void
    {
        if (!in_array($role, ['patient', 'doctor', 'admin'], true)) {
            throw new InvalidArgumentException("PatientHealthProfile::save unknown role '$role'");
        }

        $cur = self::get($conn, $patientId) ?: [];

        $height = array_key_exists('height_cm', $f) ? self::toFloatOrNull($f['height_cm']) : self::toFloatOrNull($cur['height_cm'] ?? null);
        $weight = array_key_exists('weight_kg', $f) ? self::toFloatOrNull($f['weight_kg']) : self::toFloatOrNull($cur['weight_kg'] ?? null);
        $bmi    = ($height && $weight) ? round($weight / (($height / 100) ** 2), 2) : null;

        $values = ['patient_id' => $patientId, 'bmi' => $bmi];
        foreach (self::FIELDS as $name => $type) {
            if (array_key_exists($name, $f)) {
                $v = $f[$name];
                if ($type === 'd') $v = self::toFloatOrNull($v);
                elseif ($type === 'i') $v = ($v === null || $v === '') ? null : (int) $v;
                else $v = ($v === null) ? null : (trim((string) $v) ?: null);
                $values[$name] = $v;
            } else {
                $values[$name] = $cur[$name] ?? null;
            }
        }
        $values['last_updated_by']   = $updatedBy;
        $values['last_updated_role'] = $role;

        $cols  = array_keys($values);
        $ph    = implode(',', array_fill(0, count($cols), '?'));
        $updates = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", array_diff($cols, ['patient_id'])));

        $sql = "INSERT INTO patient_health_profiles (`" . implode('`,`', $cols) . "`) VALUES ($ph)
                ON DUPLICATE KEY UPDATE $updates";
        $stmt = $conn->prepare($sql);

        $types = '';
        $bind  = [];
        foreach ($values as $c => $v) {
            if ($c === 'patient_id' || $c === 'pulse_rate' || $c === 'is_vaccinated' || $c === 'last_updated_by') {
                $types .= 'i';
            } elseif ($c === 'height_cm' || $c === 'weight_kg' || $c === 'bmi') {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $bind[] = $v;
        }
        $stmt->bind_param($types, ...$bind);
        $stmt->execute();
        $stmt->close();
    }

    private static function toFloatOrNull($v): ?float
    {
        if ($v === null || $v === '') return null;
        return is_numeric($v) ? (float) $v : null;
    }
}

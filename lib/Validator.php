<?php
/**
 * Validator — ABDM-compliant server-side input validation.
 *
 * ABDM Guideline: All validation MUST happen on a trusted server.
 * Reject failures, validate length/type/range, restrict to whitelists.
 */
class Validator
{
    private array $errors = [];

    /* ═══════════════════════════════════════════════
       CORE FLUENT API
    ═══════════════════════════════════════════════ */

    /**
     * Validate a set of fields in one call.
     *
     * $rules = [
     *   'mobile'  => ['required', 'mobile'],
     *   'otp'     => ['required', 'otp'],
     *   'aadhaar' => ['aadhaar'],
     * ]
     *
     * Returns sanitized values keyed by field name.
     */
    public function validate(array $data, array $rules): array
    {
        $clean = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? '';
            foreach ($fieldRules as $rule) {
                [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $value, $ruleName, $ruleParam);
            }
            $clean[$field] = $this->sanitizeString((string)$value);
        }
        return $clean;
    }

    public function hasErrors(): bool  { return !empty($this->errors); }
    public function getErrors(): array { return $this->errors; }
    public function firstError(): string { return array_values($this->errors)[0] ?? ''; }
    public function clearErrors(): void  { $this->errors = []; }

    /* ═══════════════════════════════════════════════
       INDIVIDUAL VALIDATORS (callable statically too)
    ═══════════════════════════════════════════════ */

    /**
     * Aadhaar number — 12 digits, Verhoeff checksum, non-zero start.
     * ABDM: NEVER store raw Aadhaar. Validate format only before encrypting.
     */
    public static function isValidAadhaar(string $aadhaar): bool
    {
        $digits = preg_replace('/\D/', '', $aadhaar);
        if (strlen($digits) !== 12) return false;
        if ($digits[0] === '0' || $digits[0] === '1') return false;
        return self::verhoeffCheck($digits);
    }

    /** 10-digit Indian mobile (starts 6-9). */
    public static function isValidMobile(string $mobile): bool
    {
        $m = preg_replace('/\D/', '', $mobile);
        return strlen($m) === 10 && preg_match('/^[6-9]/', $m);
    }

    /** 6-digit numeric OTP. */
    public static function isValidOtp(string $otp): bool
    {
        return (bool)preg_match('/^\d{6}$/', trim($otp));
    }

    /**
     * ABHA number — 14-digit, formatted XX-XXXX-XXXX-XXXX.
     * ABDM: Reject all formats outside this whitelist.
     */
    public static function isValidAbhaNumber(string $abha): bool
    {
        $digits = preg_replace('/\D/', '', $abha);
        return strlen($digits) === 14 && ctype_digit($digits);
    }

    /**
     * ABHA address — alphanumeric + dots/hyphens, ends @abdm or @<hid>.
     */
    public static function isValidAbhaAddress(string $addr): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.\-]{1,50}@[a-zA-Z]{2,20}$/', $addr);
    }

    /** Indian Driving Licence — state(2) + RTO(2-4) + year(4) + number(1-7). */
    public static function isValidDrivingLicence(string $dl): bool
    {
        return (bool)preg_match('/^[A-Z]{2}[0-9]{2}[0-9]{4}[0-9]{1,7}$/', strtoupper(str_replace([' ','-'], '', $dl)));
    }

    /** Date in YYYY-MM-DD, not future, age 0–120. */
    public static function isValidDob(string $dob): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) return false;
        $ts = strtotime($dob);
        if (!$ts || $ts > time()) return false;
        $age = (int)date_diff(date_create($dob), date_create())->y;
        return $age >= 0 && $age <= 120;
    }

    /** Gender — ABDM accepted values: M, F, O. */
    public static function isValidGender(string $g): bool
    {
        return in_array(strtoupper($g), ['M', 'F', 'O'], true);
    }

    /** Email — RFC 5322 basic. */
    public static function isValidEmail(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false
            && strlen($email) <= 254;
    }

    /**
     * Password strength: 8+ chars, uppercase, lowercase, digit, special char.
     * ABDM: Strong passwords required.
     */
    public static function isStrongPassword(string $pwd): bool
    {
        return strlen($pwd) >= 8
            && preg_match('/[A-Z]/', $pwd)
            && preg_match('/[a-z]/', $pwd)
            && preg_match('/\d/',   $pwd)
            && preg_match('/[\W_]/', $pwd);
    }

    /** Transaction ID — UUID v4 format. */
    public static function isValidTxnId(string $txn): bool
    {
        return (bool)preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $txn
        );
    }

    /* ═══════════════════════════════════════════════
       SANITIZERS
       ABDM: Encode all untrusted output; enforce UTF-8.
    ═══════════════════════════════════════════════ */

    /** Strip tags, trim, enforce UTF-8. Use for generic text fields. */
    public static function sanitizeString(string $value): string
    {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Digits only — for mobile, OTP, ABHA digits. */
    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    /** Alphanumeric uppercase — for DL numbers. */
    public static function alphanumUpper(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($value));
    }

    /** Sanitize for HTML output (XSS prevention). */
    public static function htmlEncode(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /* ═══════════════════════════════════════════════
       ABDM-SPECIFIC BATCH VALIDATORS
    ═══════════════════════════════════════════════ */

    /**
     * Validate all fields needed for Aadhaar OTP enrollment.
     * Returns sanitized data or throws on error.
     * ABDM: Reject + log on any validation failure.
     */
    public static function abdmAadhaarOtpInput(array $data): array
    {
        $aadhaar = self::digitsOnly($data['aadhaar'] ?? '');
        $consent = !empty($data['consent']);

        if (!self::isValidAadhaar($aadhaar)) {
            throw new InvalidArgumentException('Invalid Aadhaar number format or checksum');
        }
        if (!$consent) {
            throw new InvalidArgumentException('User consent is required for Aadhaar enrollment');
        }

        return ['aadhaar' => $aadhaar, 'consent' => true];
    }

    /**
     * Validate all fields needed for DL OTP enrollment.
     */
    public static function abdmDlOtpInput(array $data): array
    {
        $dl     = self::alphanumUpper($data['dl_number'] ?? '');
        $dob    = trim($data['dob'] ?? '');
        $gender = strtoupper(trim($data['gender'] ?? ''));
        $mobile = self::digitsOnly($data['mobile'] ?? '');

        if (!self::isValidDrivingLicence($dl)) {
            throw new InvalidArgumentException('Invalid Driving Licence number format');
        }
        if (!self::isValidDob($dob)) {
            throw new InvalidArgumentException('Invalid date of birth');
        }
        if (!self::isValidGender($gender)) {
            throw new InvalidArgumentException('Invalid gender value');
        }
        if (!self::isValidMobile($mobile)) {
            throw new InvalidArgumentException('Invalid mobile number');
        }

        return ['dl_number' => $dl, 'dob' => $dob, 'gender' => $gender, 'mobile' => $mobile];
    }

    /**
     * Validate ABHA link flow inputs.
     */
    public static function abdmLinkInput(array $data): array
    {
        $abhaId     = self::digitsOnly($data['abha_id'] ?? '');
        $authMethod = trim($data['auth_method'] ?? 'MOBILE_OTP');

        if (!self::isValidAbhaNumber($abhaId)) {
            throw new InvalidArgumentException('Invalid ABHA number — must be 14 digits');
        }
        if (!in_array($authMethod, ['MOBILE_OTP', 'AADHAAR_OTP'], true)) {
            throw new InvalidArgumentException('Invalid auth method');
        }

        return ['abha_id' => $abhaId, 'auth_method' => $authMethod];
    }

    /**
     * Validate OTP submission (shared by all flows).
     */
    public static function abdmOtpInput(array $data): array
    {
        $otp = trim($data['otp'] ?? '');
        if (!self::isValidOtp($otp)) {
            throw new InvalidArgumentException('OTP must be exactly 6 digits');
        }
        return ['otp' => $otp];
    }

    /* ═══════════════════════════════════════════════
       PRIVATE: RULE ENGINE
    ═══════════════════════════════════════════════ */

    private function applyRule(string $field, string $value, string $rule, ?string $param): void
    {
        switch ($rule) {
            case 'required':
                if (trim($value) === '') $this->errors[$field] = "$field is required";
                break;
            case 'mobile':
                if (!self::isValidMobile($value)) $this->errors[$field] = "Invalid mobile number";
                break;
            case 'email':
                if (!self::isValidEmail($value)) $this->errors[$field] = "Invalid email address";
                break;
            case 'otp':
                if (!self::isValidOtp($value)) $this->errors[$field] = "OTP must be 6 digits";
                break;
            case 'aadhaar':
                if (!self::isValidAadhaar($value)) $this->errors[$field] = "Invalid Aadhaar number";
                break;
            case 'abha':
                if (!self::isValidAbhaNumber($value)) $this->errors[$field] = "Invalid ABHA number";
                break;
            case 'dl':
                if (!self::isValidDrivingLicence($value)) $this->errors[$field] = "Invalid Driving Licence number";
                break;
            case 'dob':
                if (!self::isValidDob($value)) $this->errors[$field] = "Invalid date of birth";
                break;
            case 'gender':
                if (!self::isValidGender($value)) $this->errors[$field] = "Invalid gender";
                break;
            case 'password':
                if (!self::isStrongPassword($value)) $this->errors[$field] = "Password must be 8+ chars with upper, lower, digit and special character";
                break;
            case 'max':
                if (mb_strlen($value) > (int)$param) $this->errors[$field] = "$field must be at most $param characters";
                break;
            case 'min':
                if (mb_strlen($value) < (int)$param) $this->errors[$field] = "$field must be at least $param characters";
                break;
            case 'in':
                if (!in_array($value, explode(',', $param ?? ''), true)) $this->errors[$field] = "Invalid value for $field";
                break;
        }
    }

    /* ═══════════════════════════════════════════════
       PRIVATE: VERHOEFF CHECKSUM (Aadhaar)
    ═══════════════════════════════════════════════ */

    private static function verhoeffCheck(string $num): bool
    {
        $d = [
            [0,1,2,3,4,5,6,7,8,9],
            [1,2,3,4,0,6,7,8,9,5],
            [2,3,4,0,1,7,8,9,5,6],
            [3,4,0,1,2,8,9,5,6,7],
            [4,0,1,2,3,9,5,6,7,8],
            [5,9,8,7,6,0,4,3,2,1],
            [6,5,9,8,7,1,0,4,3,2],
            [7,6,5,9,8,2,1,0,4,3],
            [8,7,6,5,9,3,2,1,0,4],
            [9,8,7,6,5,4,3,2,1,0],
        ];
        $p = [
            [0,1,2,3,4,5,6,7,8,9],
            [1,5,7,6,2,8,3,0,9,4],
            [5,8,0,3,7,9,6,1,4,2],
            [8,9,1,6,0,4,3,5,2,7],
            [9,4,5,3,1,2,6,8,7,0],
            [4,2,8,6,5,7,3,9,0,1],
            [2,7,9,3,8,0,6,4,1,5],
            [7,0,4,6,9,1,3,2,5,8],
        ];
        $inv = [0,4,3,2,1,9,8,7,6,5];

        $check = 0;
        $digits = array_reverse(str_split($num));
        foreach ($digits as $i => $digit) {
            $check = $d[$check][$p[$i % 8][(int)$digit]];
        }
        return $check === 0;
    }
}

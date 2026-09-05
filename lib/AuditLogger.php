<?php
/**
 * AuditLogger — ABDM-compliant audit log management.
 *
 * ABDM Requirements:
 *  - Retain general logs: 3 months online, 2 years archived.
 *  - Retain Aadhaar auth logs: 2 years online, 5 years archived.
 *  - Mandatory fields: terminal_id, auth_modality, txn_id, timestamp,
 *    aua_code, user_consent, auth_status.
 *  - PROHIBITED: Raw Aadhaar number, biometric PID, session keys (skey), HMAC.
 *  - Log: auth failures, validation failures, access violations, API anomalies.
 */
class AuditLogger
{
    private mysqli $conn;
    private string $terminalId;
    private string $auaCode;

    /* ABDM prohibited fields — NEVER appear in any log entry */
    private const PROHIBITED_KEYS = [
        'aadhaar', 'aadhar', 'uid', 'pid', 'skey', 'hmac',
        'password', 'client_secret', 'otp', 'biometric',
        'session_key', 'private_key',
    ];

    public function __construct(mysqli $conn)
    {
        $this->conn       = $conn;
        $this->terminalId = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->auaCode    = defined('ABDM_CLIENT_ID') ? ABDM_CLIENT_ID : '';
    }

    /* ═══════════════════════════════════════════════
       PRIMARY LOG METHODS
    ═══════════════════════════════════════════════ */

    /**
     * Log an ABDM / ABHA authentication event.
     *
     * ABDM Mandatory Fields: terminal_id, auth_modality, txn_id, ts, ac, consent, status.
     * PROHIBITED: raw Aadhaar, PID block, skey, HMAC.
     *
     * $modality : 'OTP' | 'MOBILE_OTP' | 'AADHAAR_OTP' | 'DL'
     * $status   : 'SUCCESS' | 'FAILURE' | 'PENDING'
     */
    public function logAbhaAuth(
        int    $entityId,
        string $entityType,     // 'user' | 'member'
        string $modality,
        string $txnId,
        string $status,
        string $consentToken = 'Y',
        array  $extra = []
    ): void {
        $safeExtra = $this->stripProhibited($extra);

        $this->insert('abha_auth', [
            'entity_id'    => $entityId,
            'entity_type'  => $entityType,
            'terminal_id'  => $this->terminalId,
            'auth_modality'=> $modality,
            'txn_id'       => $txnId,
            'aua_code'     => $this->auaCode,
            'user_consent' => $consentToken,
            'auth_status'  => $status,
            'ip_address'   => $this->clientIp(),
            'user_agent'   => $this->safeUserAgent(),
            'extra_data'   => $safeExtra ? json_encode($safeExtra) : null,
            'log_type'     => 'AADHAAR_AUTH',  // triggers 2y/5y retention
        ]);
    }

    /**
     * Log an ABHA-recovery ("Forgot ABHA") lookup — an OTP-authenticated
     * read of which ABHA account(s) sit behind an Aadhaar / mobile. This is
     * NOT a login (no session is created), but it is an Aadhaar/mobile OTP
     * auth event, so it gets the AADHAAR_AUTH retention class.
     *
     * $modality : 'AADHAAR_OTP' | 'MOBILE_OTP'
     * $status   : 'PENDING' (OTP sent) | 'SUCCESS' | 'FAILURE'
     */
    public function logAbhaRecovery(
        string $modality,
        string $txnId,
        string $status,
        array  $extra = []
    ): void {
        $this->insert('abha_recovery', [
            'entity_id'     => 0,
            'entity_type'   => 'user',
            'terminal_id'   => $this->terminalId,
            'auth_modality' => $modality,
            'txn_id'        => $txnId,
            'aua_code'      => $this->auaCode,
            'user_consent'  => 'Y',
            'auth_status'   => $status,
            'ip_address'    => $this->clientIp(),
            'user_agent'    => $this->safeUserAgent(),
            'extra_data'    => ($e = $this->stripProhibited($extra)) ? json_encode($e) : null,
            'log_type'      => 'AADHAAR_AUTH',
        ]);
    }

    /**
     * Log an inbound HI Consent notify (the CM tells us a consent was
     * GRANTED / REVOKED). event_type 'hi_consent_notify'.
     *
     * $consentStatus : 'GRANTED' | 'REVOKED' | 'UNKNOWN'
     * $status        : 'SUCCESS' | 'FAILURE'
     */
    public function logHiConsentNotify(string $consentStatus, string $consentId, string $status, array $extra = []): void
    {
        $this->insert('hi_consent_notify', [
            'entity_id'     => 0,
            'entity_type'   => 'consent',
            'auth_modality' => $consentStatus,
            'txn_id'        => $consentId,
            'auth_status'   => $status,
            'ip_address'    => $this->clientIp(),
            'user_agent'    => $this->safeUserAgent(),
            'extra_data'    => ($e = $this->stripProhibited($extra)) ? json_encode($e) : null,
            'log_type'      => 'CONSENT',
        ]);
    }

    /**
     * Log an inbound HI health-information request from a HIU (referencing a
     * consent). event_type 'hi_data_request'.
     *
     * $status : 'ACKNOWLEDGED' | 'FAILURE'
     */
    public function logHiDataRequest(string $consentId, string $transactionId, string $status, array $extra = []): void
    {
        $this->insert('hi_data_request', [
            'entity_id'     => 0,
            'entity_type'   => 'consent',
            'auth_modality' => 'HI_REQUEST',
            'txn_id'        => $transactionId !== '' ? $transactionId : $consentId,
            'auth_status'   => $status,
            'resource'      => $consentId,
            'ip_address'    => $this->clientIp(),
            'user_agent'    => $this->safeUserAgent(),
            'extra_data'    => ($e = $this->stripProhibited($extra)) ? json_encode($e) : null,
            'log_type'      => 'CONSENT',
        ]);
    }

    /**
     * Log an authentication attempt (login, OTP verify, ABHA login).
     * ABDM: Log ALL authentication failures.
     */
    public function logAuthAttempt(
        string $identifier,    // email / mobile (not Aadhaar)
        string $method,        // 'password' | 'otp' | 'abha' | 'aadhaar_login'
        bool   $success,
        int    $entityId = 0,
        string $entityType = 'user'
    ): void {
        // Never log raw Aadhaar as identifier
        if ($this->looksLikeAadhaar($identifier)) {
            $identifier = '[MASKED_UID]';
        }

        $this->insert('auth_attempt', [
            'entity_id'   => $entityId,
            'entity_type' => $entityType,
            'identifier'  => $identifier,
            'auth_method' => $method,
            'auth_status' => $success ? 'SUCCESS' : 'FAILURE',
            'ip_address'  => $this->clientIp(),
            'user_agent'  => $this->safeUserAgent(),
            'log_type'    => 'AUTH',
        ]);
    }

    /**
     * Log an input validation failure.
     * ABDM: Log all validation failures.
     */
    public function logValidationFailure(
        string $field,
        string $reason,
        int    $entityId = 0,
        string $entityType = 'user'
    ): void {
        $this->insert('validation_failure', [
            'entity_id'   => $entityId,
            'entity_type' => $entityType,
            'field_name'  => $field,
            'reason'      => $reason,
            'ip_address'  => $this->clientIp(),
            'user_agent'  => $this->safeUserAgent(),
            'log_type'    => 'VALIDATION',
        ]);
    }

    /**
     * Log an access control violation.
     * ABDM: Log all access control failures (deny by default).
     */
    public function logAccessViolation(
        string $resource,
        string $reason = 'Unauthorized',
        int    $entityId = 0
    ): void {
        $this->insert('access_violation', [
            'entity_id'  => $entityId,
            'resource'   => $resource,
            'reason'     => $reason,
            'ip_address' => $this->clientIp(),
            'user_agent' => $this->safeUserAgent(),
            'log_type'   => 'ACCESS_VIOLATION',
        ]);
    }

    /**
     * Log an API call anomaly (rate limit, bad schema, unexpected error).
     * ABDM: Log all API access anomalies for SIEM consumption.
     */
    public function logApiAnomaly(
        string $endpoint,
        string $action,
        string $reason,
        int    $entityId = 0,
        int    $httpCode = 0
    ): void {
        $this->insert('api_anomaly', [
            'entity_id'   => $entityId,
            'endpoint'    => $endpoint,
            'action'      => $action,
            'reason'      => $reason,
            'http_code'   => $httpCode,
            'ip_address'  => $this->clientIp(),
            'user_agent'  => $this->safeUserAgent(),
            'log_type'    => 'API_ANOMALY',
        ]);
    }

    /**
     * Log a data access event (who accessed what patient data and when).
     * ABDM: Least privilege — track every data access.
     */
    public function logDataAccess(
        int    $accessorId,
        string $accessorType,
        int    $patientId,
        string $dataType,       // e.g. 'medical_report', 'abha_profile'
        string $purpose = ''
    ): void {
        $this->insert('data_access', [
            'accessor_id'   => $accessorId,
            'accessor_type' => $accessorType,
            'patient_id'    => $patientId,
            'data_type'     => $dataType,
            'purpose'       => $purpose,
            'ip_address'    => $this->clientIp(),
            'log_type'      => 'DATA_ACCESS',
        ]);
    }

    /* ═══════════════════════════════════════════════
       RETENTION POLICY HELPERS
       ABDM: General = 3 months online, 2 years archive.
             Aadhaar auth = 2 years online, 5 years archive.
    ═══════════════════════════════════════════════ */

    /**
     * Archive logs that have exceeded their online retention period.
     * Call this from a scheduled task (cron) — NOT per-request.
     *
     * Returns count of rows archived.
     */
    public function archiveExpiredLogs(): int
    {
        $archived = 0;

        // General logs: online retention 3 months
        $cutoffGeneral = date('Y-m-d H:i:s', strtotime('-3 months'));

        // Aadhaar auth logs: online retention 2 years
        $cutoffAadhaar = date('Y-m-d H:i:s', strtotime('-2 years'));

        $result = $this->conn->query("
            SELECT * FROM abdm_audit_logs
            WHERE (log_type = 'AADHAAR_AUTH' AND created_at < '$cutoffAadhaar')
               OR (log_type != 'AADHAAR_AUTH' AND created_at < '$cutoffGeneral')
        ");

        if (!$result) return 0;

        while ($row = $result->fetch_assoc()) {
            // Write to archive table
            $data    = $this->conn->real_escape_string(json_encode($row));
            $logType = $this->conn->real_escape_string($row['log_type']);
            $created = $this->conn->real_escape_string($row['created_at']);

            $this->conn->query("
                INSERT IGNORE INTO abdm_audit_archive (log_id, log_type, log_data, original_created_at)
                VALUES ({$row['id']}, '$logType', '$data', '$created')
            ");

            if ($this->conn->affected_rows > 0) {
                $this->conn->query("DELETE FROM abdm_audit_logs WHERE id = {$row['id']}");
                $archived++;
            }
        }

        return $archived;
    }

    /**
     * Purge archive rows beyond the maximum retention period.
     * General: 2 years. Aadhaar auth: 5 years.
     */
    public function purgeExpiredArchive(): int
    {
        $cutoffGeneral = date('Y-m-d', strtotime('-2 years'));
        $cutoffAadhaar = date('Y-m-d', strtotime('-5 years'));

        $this->conn->query("
            DELETE FROM abdm_audit_archive
            WHERE (log_type = 'AADHAAR_AUTH' AND original_created_at < '$cutoffAadhaar')
               OR (log_type != 'AADHAAR_AUTH' AND original_created_at < '$cutoffGeneral')
        ");

        return (int)$this->conn->affected_rows;
    }

    /* ═══════════════════════════════════════════════
       PRIVATE HELPERS
    ═══════════════════════════════════════════════ */

    private function insert(string $event, array $data): void
    {
        $data['event_type'] = $event;
        $data['created_at'] = date('Y-m-d H:i:s');

        // Build parameterized insert
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $types  = implode('', array_map(fn($v) => is_int($v) ? 'i' : 's', array_values($data)));

        $stmt = $this->conn->prepare(
            "INSERT INTO abdm_audit_logs ($cols) VALUES ($places)"
        );

        if (!$stmt) {
            // Fallback: write to PHP error log so nothing is silently lost
            error_log('[AuditLogger] DB prepare failed: ' . $this->conn->error
                . ' | Data: ' . json_encode($data));
            return;
        }

        $values = array_values($data);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Remove any prohibited keys from the extra data array.
     * ABDM: NEVER log raw Aadhaar, PID, skey, HMAC, passwords.
     */
    private function stripProhibited(array $data): array
    {
        return array_filter(
            $data,
            fn($key) => !in_array(strtolower($key), self::PROHIBITED_KEYS, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /** Check if a string looks like a 12-digit Aadhaar number (to auto-mask). */
    private function looksLikeAadhaar(string $str): bool
    {
        return (bool)preg_match('/^\d{12}$/', preg_replace('/\D/', '', $str));
    }

    private function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    private function safeUserAgent(): string
    {
        return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
    }
}

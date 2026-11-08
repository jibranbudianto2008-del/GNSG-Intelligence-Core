<?php
declare(strict_types=1);

namespace GNSG\Audit;

use PDO;
use InvalidArgumentException;
use RuntimeException;
use JsonException;

final class AuditLogger
{
    private const ALLOWED_STATUSES = [
        'success',
        'failed',
        'denied',
        'pending',
    ];

    private const SENSITIVE_KEYS = [
        'password',
        'token',
        'api_key',
        'apikey',
        'secret',
        'authorization',
        'cookie',
        'private_key',
        'access_token',
        'refresh_token',
    ];

    public function __construct(
        private readonly PDO $database
    ) {}

    public function log(array $event): void
    {
        $action = $event['action'] ?? null;
        $status = $event['status'] ?? null;

        if (!is_string($action) || $action === '' || strlen($action) > 100) {
            throw new InvalidArgumentException('Invalid audit action.');
        }

        if (!is_string($status) ||
            !in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid audit status.');
        }

        $userId = $event['user_id'] ?? null;

        if ($userId !== null &&
            (!is_int($userId) || $userId <= 0)) {
            throw new InvalidArgumentException('Invalid audit user ID.');
        }

        $resourceType = $event['resource_type'] ?? null;
        $resourceId = $event['resource_id'] ?? null;

        foreach ([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ] as $field => $value) {
            if ($value !== null &&
                (!is_string($value) || strlen($value) > 100)) {
                throw new InvalidArgumentException(
                    'Invalid audit ' . $field . '.'
                );
            }
        }

        $ipAddress = $event['ip_address'] ?? null;

        if ($ipAddress !== null &&
            (!is_string($ipAddress) ||
             filter_var($ipAddress, FILTER_VALIDATE_IP) === false)) {
            throw new InvalidArgumentException('Invalid audit IP address.');
        }

        $details = $event['details'] ?? null;

        if ($details !== null && !is_array($details)) {
            throw new InvalidArgumentException(
                'Audit details must be an array or null.'
            );
        }

        $safeDetails = $details !== null
            ? self::redactSensitiveData($details)
            : null;

        try {
            $detailsJson = $safeDetails !== null
                ? json_encode(
                    $safeDetails,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                )
                : null;
        } catch (JsonException $e) {
            throw new RuntimeException(
                'Audit details could not be encoded.'
            );
        }

        $sql = '
            INSERT INTO audit_logs (
                user_id,
                action,
                resource_type,
                resource_id,
                status,
                details,
                ip_address
            ) VALUES (
                :user_id,
                :action,
                :resource_type,
                :resource_id,
                :status,
                :details,
                :ip_address
            )
        ';

        try {
   	    $statement = $this->database->prepare($sql);

   	    $statement->execute([
       		':user_id' => $userId,
       		':action' => $action,
       		':resource_type' => $resourceType,
       		':resource_id' => $resourceId,
       		':status' => $status,
       		':details' => $detailsJson,
       		':ip_address' => $ipAddress,
   	    ]);
	} catch (\PDOException $e) {
   	    error_log('GNSG Audit Logger: database write failed.');

   	    throw new RuntimeException(
       		'Audit logging service is currently unavailable.'
   	    );
	}
    }

    private static function redactSensitiveData(array $data): array
    {
        $safe = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower(
                preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key)
            );

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $safe[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = self::redactSensitiveData($value);
                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }
}

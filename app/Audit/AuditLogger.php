<?php
declare(strict_types=1);

namespace GNSG\Audit;

use PDO;
use PDOException;
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

    private const SENSITIVE_KEY_PARTS = [
        'password',
        'token',
        'secret',
        'authorization',
        'cookie',
        'credential',
    ];

    private const MAX_DETAILS_DEPTH = 8;
    private const MAX_DETAILS_ITEMS = 200;
    private const MAX_DETAILS_STRING_BYTES = 4096;
    private const MAX_DETAILS_JSON_BYTES = 16384;

    public function __construct(
        private readonly PDO $database
    ) {
    }

    public function log(array $event): void
    {
        $action = $event['action'] ?? null;
        $status = $event['status'] ?? null;

        if (
            !is_string($action)
            || trim($action) === ''
            || strlen($action) > 100
            || preg_match('/[\x00-\x1F\x7F]/', $action) === 1
        ) {
            throw new InvalidArgumentException(
                'Invalid audit action.'
            );
        }

        if (
            !is_string($status)
            || !in_array($status, self::ALLOWED_STATUSES, true)
        ) {
            throw new InvalidArgumentException(
                'Invalid audit status.'
            );
        }

        $userId = $event['user_id'] ?? null;

        if (
            $userId !== null
            && (!is_int($userId) || $userId <= 0)
        ) {
            throw new InvalidArgumentException(
                'Invalid audit user ID.'
            );
        }

        $resourceType = $event['resource_type'] ?? null;
        $resourceId = $event['resource_id'] ?? null;

        foreach ([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ] as $field => $value) {
            if (
                $value !== null
                && (!is_string($value) || strlen($value) > 100)
            ) {
                throw new InvalidArgumentException(
                    'Invalid audit ' . $field . '.'
                );
            }
        }

        $ipAddress = $event['ip_address'] ?? null;

        if (
            $ipAddress !== null
            && (
                !is_string($ipAddress)
                || filter_var(
                    $ipAddress,
                    FILTER_VALIDATE_IP
                ) === false
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid audit IP address.'
            );
        }

        $details = $event['details'] ?? null;

        if ($details !== null && !is_array($details)) {
            throw new InvalidArgumentException(
                'Audit details must be an array or null.'
            );
        }

        $itemCount = 0;

        $safeDetails = $details !== null
            ? self::redactSensitiveData(
                $details,
                0,
                $itemCount
            )
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

        if (
            $detailsJson !== null
            && strlen($detailsJson) > self::MAX_DETAILS_JSON_BYTES
        ) {
            throw new InvalidArgumentException(
                'Audit details exceed maximum encoded size.'
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
        } catch (PDOException $e) {
            error_log(
                'GNSG Audit Logger: database write failed.'
            );

            throw new RuntimeException(
                'Audit logging service is currently unavailable.'
            );
        }
    }

    private static function redactSensitiveData(
        array $data,
        int $depth,
        int &$itemCount
    ): array {
        if ($depth > self::MAX_DETAILS_DEPTH) {
            throw new InvalidArgumentException(
                'Audit details exceed maximum nesting depth.'
            );
        }

        $safe = [];

        foreach ($data as $key => $value) {
            $itemCount++;

            if ($itemCount > self::MAX_DETAILS_ITEMS) {
                throw new InvalidArgumentException(
                    'Audit details exceed maximum element count.'
                );
            }

            $normalizedKey = preg_replace(
                '/([a-z0-9])([A-Z])/',
                '$1_$2',
                (string) $key
            );

            $normalizedKey = strtolower(
                preg_replace(
                    '/[^a-zA-Z0-9]+/',
                    '_',
                    $normalizedKey
                )
            );

            $parts = array_filter(
                explode('_', trim($normalizedKey, '_')),
                static fn (string $part): bool => $part !== ''
            );

            $isSensitive = false;

            foreach ($parts as $part) {
                if (
                    in_array(
                        $part,
                        self::SENSITIVE_KEY_PARTS,
                        true
                    )
                ) {
                    $isSensitive = true;
                    break;
                }
            }

            if (!$isSensitive) {
                $combinedKey = implode('_', $parts);

                $isSensitive =
                    $combinedKey === 'apikey'
                    || $combinedKey === 'session_id'
                    || (
                        count($parts) >= 2
                        && in_array(
                            $parts[0],
                            ['api', 'private'],
                            true
                        )
                        && $parts[1] === 'key'
                    );
            }

            if ($isSensitive) {
                $safe[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = self::redactSensitiveData(
                    $value,
                    $depth + 1,
                    $itemCount
                );

                continue;
            }

            if (is_string($value)) {
                if (
                    strlen($value) > self::MAX_DETAILS_STRING_BYTES
                ) {
                    throw new InvalidArgumentException(
                        'Audit details contain an oversized string value.'
                    );
                }

                $safe[$key] = $value;
                continue;
            }

            if (
                $value === null
                || is_bool($value)
                || is_int($value)
                || is_float($value)
            ) {
                $safe[$key] = $value;
                continue;
            }

            throw new InvalidArgumentException(
                'Audit details contain an unsupported data type.'
            );
        }

        return $safe;
    }
}

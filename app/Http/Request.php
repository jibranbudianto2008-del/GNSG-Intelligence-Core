<?php

declare(strict_types=1);

namespace GNSG\Http;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class Request
{
    public const MAX_BODY_BYTES = 1_048_576;

    private readonly string $method;
    private readonly string $path;
    private readonly array $queryParams;
    private readonly array $headers;
    private readonly string $body;

    public function __construct(
        string $method,
        string $path,
        array $queryParams = [],
        array $headers = [],
        string $body = ''
    ) {
        $method = strtoupper($method);

        if (
            !preg_match('/\A[A-Z]{1,20}\z/', $method)
        ) {
            throw new InvalidArgumentException(
                'Invalid HTTP method.'
            );
        }

        if (
            $path === ''
            || $path[0] !== '/'
            || strlen($path) > 4096
            || preg_match('/[\x00-\x20\x7F\\\\]/', $path) === 1
            || preg_match('/%(?![a-fA-F0-9]{2})/', $path) === 1
        ) {
            throw new InvalidArgumentException(
                'Invalid request path.'
            );
        }

        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new InvalidArgumentException(
                'Request body exceeds maximum size.'
            );
        }

        $normalizedHeaders = [];

        foreach ($headers as $name => $value) {
            if (
                !is_string($name)
                || !preg_match('/\A[a-zA-Z0-9-]+\z/', $name)
                || !is_string($value)
                || preg_match(
                    '/[\r\n\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                    $value
                ) === 1
            ) {
                throw new InvalidArgumentException(
                    'Invalid request header.'
                );
            }

            $normalizedName = strtolower($name);

            if (array_key_exists($normalizedName, $normalizedHeaders)) {
                throw new InvalidArgumentException(
                    'Duplicate request header.'
                );
            }

            $normalizedHeaders[$normalizedName] = trim(
                $value,
                " \t"
            );
        }

        $this->method = $method;
        $this->path = $path;
        $this->queryParams = $queryParams;
        $this->headers = $normalizedHeaders;
        $this->body = $body;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (!is_string($method) || !is_string($uri)) {
            throw new RuntimeException(
                'Invalid HTTP request metadata.'
            );
        }

        if (
            strlen($uri) > 8192
            || preg_match('/[\x00-\x20\x7F]/', $uri) === 1
        ) {
            throw new RuntimeException(
                'Invalid request URI.'
            );
        }

        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path)) {
            throw new RuntimeException(
                'Unable to parse request path.'
            );
        }

        $headers = [];

        if (function_exists('getallheaders')) {
            $serverHeaders = getallheaders();

            if (is_array($serverHeaders)) {
                $headers = $serverHeaders;
            }
        }

        // Fallback for environments without getallheaders().
        if ($headers === []) {
            foreach ($_SERVER as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }

                if (str_starts_with($key, 'HTTP_')) {
                    $name = str_replace(
                        '_',
                        '-',
                        substr($key, 5)
                    );

                    $headers[$name] = $value;
                }
            }

            foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $key) {
                if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
                    $name = str_replace('_', '-', $key);

                    $headers[$name] = $_SERVER[$key];
                }
            }
        }

        $stream = fopen('php://input', 'rb');

        if ($stream === false) {
            throw new RuntimeException(
                'Unable to read request body.'
            );
        }

        try {
            $body = stream_get_contents(
                $stream,
                self::MAX_BODY_BYTES + 1
            );
        } finally {
            fclose($stream);
        }

        if ($body === false) {
            throw new RuntimeException(
                'Unable to read request body.'
            );
        }

        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new RuntimeException(
                'Request body exceeds maximum size.'
            );
        }

        return new self(
            $method,
            $path,
            is_array($_GET) ? $_GET : [],
            $headers,
            $body
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function queryParams(): array
    {
        return $this->queryParams;
    }

    public function query(
        string $key,
        mixed $default = null
    ): mixed {
        return $this->queryParams[$key] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(
        string $name,
        ?string $default = null
    ): ?string {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function isJson(): bool
    {
        $contentType = $this->header('content-type');

        if ($contentType === null) {
            return false;
        }

        $mediaType = strtolower(
            trim(explode(';', $contentType, 2)[0])
        );

        return $mediaType === 'application/json'
            || preg_match(
                '/\Aapplication\/[a-z0-9.+-]+\+json\z/',
                $mediaType
            ) === 1;
    }

    public function json(): mixed
    {
        try {
            return json_decode(
                $this->body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                'Request body contains invalid JSON.',
                0,
                $e
            );
        }
    }
}

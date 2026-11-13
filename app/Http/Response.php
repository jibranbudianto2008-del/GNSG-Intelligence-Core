<?php

declare(strict_types=1);

namespace GNSG\Http;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class Response
{
    private readonly int $statusCode;

    private string $body;

    private array $headers = [];

    private bool $sent = false;

    public function __construct(
        string $body = '',
        int $statusCode = 200,
        array $headers = []
    ) {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new InvalidArgumentException(
                'Invalid HTTP response status code.'
            );
        }

        $this->body = $body;
        $this->statusCode = $statusCode;

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    public static function json(
        mixed $data,
        int $statusCode = 200,
        array $headers = []
    ): self {
        try {
            $body = json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $e) {
            throw new RuntimeException(
                'Unable to encode JSON response.'
            );
        }

        $response = new self(
            $body,
            $statusCode,
            $headers
        );

        $response->setHeader(
            'Content-Type',
            'application/json; charset=utf-8'
        );

        return $response;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(
        string $name,
        ?string $default = null
    ): ?string {
        $normalizedName = strtolower($name);

        foreach ($this->headers as $headerName => $value) {
            if (strtolower($headerName) === $normalizedName) {
                return $value;
            }
        }

        return $default;
    }

    public function setHeader(
        string $name,
        string $value
    ): void {
        if ($this->sent) {
            throw new RuntimeException(
                'Cannot modify a sent response.'
            );
        }

        if (
            !preg_match('/\A[a-zA-Z0-9!#$%&\'*+.^_`|~-]+\z/', $name)
            || preg_match(
                '/[\r\n\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                $value
            ) === 1
        ) {
            throw new InvalidArgumentException(
                'Invalid HTTP response header.'
            );
        }

        foreach (array_keys($this->headers) as $existingName) {
            if (strtolower($existingName) === strtolower($name)) {
                unset($this->headers[$existingName]);
            }
        }

        $this->headers[$name] = trim($value, " \t");
    }

    public function send(): void
    {
        if ($this->sent) {
            throw new RuntimeException(
                'Response has already been sent.'
            );
        }

        if (headers_sent()) {
            throw new RuntimeException(
                'Cannot send response after headers have been sent.'
            );
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        $this->sent = true;

        echo $this->body;
    }

    public function isSent(): bool
    {
        return $this->sent;
    }
}

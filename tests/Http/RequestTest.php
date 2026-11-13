<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Http/Request.php';

use GNSG\Http\Request;

$passed = 0;
$failed = 0;

$assert = static function (
    bool $condition,
    string $description
) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "[PASS] {$description}\n";
        return;
    }

    $failed++;
    echo "[FAIL] {$description}\n";
};

$assertThrows = static function (
    callable $callback,
    string $description,
    string $expectedClass = InvalidArgumentException::class
) use ($assert): void {
    try {
        $callback();
        $assert(false, $description);
    } catch (Throwable $e) {
        $assert(
            $e instanceof $expectedClass,
            $description
        );
    }
};

// 1. Basic request construction.
$request = new Request(
    'get',
    '/api/users',
    ['page' => '2'],
    ['Content-Type' => 'application/json'],
    '{"active":true}'
);

$assert(
    $request->method() === 'GET',
    'HTTP method is normalized to uppercase'
);

$assert(
    $request->path() === '/api/users',
    'Request path is preserved'
);

$assert(
    $request->query('page') === '2',
    'Query parameter can be retrieved'
);

$assert(
    $request->query('missing', 'default') === 'default',
    'Query parameter supports default value'
);

$assert(
    $request->header('CONTENT-TYPE') === 'application/json',
    'Header lookup is case-insensitive'
);

$assert(
    $request->body() === '{"active":true}',
    'Request body is preserved'
);

// 2. HTTP method validation.
$assertThrows(
    static fn() => new Request('', '/'),
    'Empty HTTP method is rejected'
);

$assertThrows(
    static fn() => new Request('GET INVALID', '/'),
    'HTTP method containing spaces is rejected'
);

$assert(
    (new Request('GET', '/'))->method() === 'GET',
    'Valid GET request can be constructed'
);

// 3. Path validation.
$assertThrows(
    static fn() => new Request('GET', 'api/users'),
    'Relative request path is rejected'
);

$assertThrows(
    static fn() => new Request('GET', "/api/\nusers"),
    'Path containing control characters is rejected'
);

$assertThrows(
    static fn() => new Request('GET', '/api/%ZZ'),
    'Malformed percent encoding is rejected'
);

// 4. Header validation.
$assertThrows(
    static fn() => new Request(
        'GET',
        '/',
        [],
        ['Bad Header' => 'value']
    ),
    'Invalid header name is rejected'
);

$assertThrows(
    static fn() => new Request(
        'GET',
        '/',
        [],
        ['X-Test' => "safe\r\nInjected: true"]
    ),
    'Header CRLF injection is rejected'
);

$assertThrows(
    static fn() => new Request(
        'GET',
        '/',
        [],
        [
            'Content-Type' => 'application/json',
            'content-type' => 'text/plain',
        ]
    ),
    'Duplicate headers with different casing are rejected'
);

// 5. Body size validation.
$assertThrows(
    static fn() => new Request(
        'POST',
        '/',
        [],
        [],
        str_repeat('a', Request::MAX_BODY_BYTES + 1)
    ),
    'Request body exceeding maximum size is rejected'
);

// 6. JSON handling.
$jsonRequest = new Request(
    'POST',
    '/api/test',
    [],
    ['Content-Type' => 'application/json; charset=utf-8'],
    '{"name":"GNSG"}'
);

$assert(
    $jsonRequest->isJson(),
    'JSON content type is recognized'
);

$assert(
    $jsonRequest->json() === ['name' => 'GNSG'],
    'Valid JSON body is decoded'
);

$vendorJsonRequest = new Request(
    'POST',
    '/api/test',
    [],
    ['Content-Type' => 'application/vnd.gnsg+json'],
    '{"status":"ok"}'
);

$assert(
    $vendorJsonRequest->isJson(),
    'Vendor-specific JSON content type is recognized'
);

$plainRequest = new Request(
    'POST',
    '/api/test',
    [],
    ['Content-Type' => 'text/plain'],
    'hello'
);

$assert(
    !$plainRequest->isJson(),
    'Non-JSON content type is not recognized as JSON'
);

$invalidJsonRequest = new Request(
    'POST',
    '/api/test',
    [],
    ['Content-Type' => 'application/json'],
    '{"invalid":'
);

$assertThrows(
    static fn() => $invalidJsonRequest->json(),
    'Malformed JSON body is rejected'
);

// 7. Final result.
echo "\n";
echo "Request tests passed: {$passed}\n";
echo "Request tests failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

echo "ALL REQUEST TESTS PASSED.\n";

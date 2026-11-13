<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Http/Response.php';

ob_start();

use GNSG\Http\Response;

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

// 1. Basic response construction.
$response = new Response(
    'GNSG Intelligence Core',
    200,
    ['X-Application' => 'GNSG']
);

$assert(
    $response->statusCode() === 200,
    'Response status code is preserved'
);

$assert(
    $response->body() === 'GNSG Intelligence Core',
    'Response body is preserved'
);

$assert(
    $response->header('x-application') === 'GNSG',
    'Response header lookup is case-insensitive'
);

$assert(
    $response->isSent() === false,
    'New response has not been sent'
);

// 2. Status code validation.
$assertThrows(
    static fn() => new Response('', 99),
    'Status code below 100 is rejected'
);

$assertThrows(
    static fn() => new Response('', 600),
    'Status code above 599 is rejected'
);

// 3. Header validation and replacement.
$headerResponse = new Response();

$headerResponse->setHeader('X-Test', 'first');
$headerResponse->setHeader('x-test', 'second');

$assert(
    $headerResponse->header('X-TEST') === 'second',
    'Setting an existing header replaces its value case-insensitively'
);

$assert(
    count($headerResponse->headers()) === 1,
    'Replacing a header does not create duplicate entries'
);

$assertThrows(
    static fn() => $headerResponse->setHeader(
        'Invalid Header',
        'value'
    ),
    'Invalid response header name is rejected'
);

$assertThrows(
    static fn() => $headerResponse->setHeader(
        'X-Test',
        "safe\r\nInjected: true"
    ),
    'Response header CRLF injection is rejected'
);

// 4. JSON response.
$jsonResponse = Response::json(
    ['status' => 'ok', 'project' => 'GNSG'],
    201,
    ['X-Request-Type' => 'test']
);

$assert(
    $jsonResponse->statusCode() === 201,
    'JSON response preserves custom status code'
);

$assert(
    $jsonResponse->header('content-type')
        === 'application/json; charset=utf-8',
    'JSON response sets the correct content type'
);

$decoded = json_decode(
    $jsonResponse->body(),
    true
);

$assert(
    $decoded === [
        'status' => 'ok',
        'project' => 'GNSG',
    ],
    'JSON response body contains correctly encoded data'
);

$assert(
    $jsonResponse->header('X-Request-Type') === 'test',
    'JSON response preserves additional headers'
);

// 5. JSON encoding failure.
$assertThrows(
    static fn() => Response::json("\xB1"),
    'Invalid UTF-8 JSON data is rejected',
    RuntimeException::class
);

// 6. Send response and verify state.
$sendResponse = new Response(
    'HTTP response test',
    202,
    ['X-Test-Send' => 'yes']
);

ob_start();

try {
    $sendResponse->send();
    $output = ob_get_contents();
} finally {
    ob_end_clean();
}

$assert(
    $output === 'HTTP response test',
    'Sending response outputs the expected body'
);

$assert(
    $sendResponse->isSent(),
    'Response state changes after sending'
);

$assertThrows(
    static fn() => $sendResponse->send(),
    'Sending the same response twice is rejected',
    RuntimeException::class
);

$assertThrows(
    static fn() => $sendResponse->setHeader('X-Late', 'value'),
    'Modifying headers after sending is rejected',
    RuntimeException::class
);

// 7. Final result.
echo "\n";
echo "Response tests passed: {$passed}\n";
echo "Response tests failed: {$failed}\n";

$testOutput = ob_get_clean();
echo $testOutput;

if ($failed > 0) {
    exit(1);
}

echo "ALL RESPONSE TESTS PASSED.\n";

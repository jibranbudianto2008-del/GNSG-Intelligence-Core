<?php

declare(strict_types=1);

namespace GNSG\Http\Controller;

use GNSG\Auth\AuthManager;
use GNSG\Security\CsrfProtection;
use GNSG\Http\Request;
use GNSG\Http\Response;
use JsonException;

final class AuthController
{
    private const MAX_BODY_BYTES = 8192;

    public function __construct(
        private AuthManager $authManager
    ) {
    }

    public function login(
        Request $request,
        array $params
    ): Response {
        if (!$request->isJson()) {
            return Response::json([
                'error' => 'Content-Type must be application/json.',
            ], 415);
        }

        $body = $request->body();

        if (strlen($body) > self::MAX_BODY_BYTES) {
            return Response::json([
                'error' => 'Request body is too large.',
            ], 413);
        }

        try {
            $payload = json_decode(
                $body,
                false,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return Response::json([
                'error' => 'Malformed JSON payload.',
            ], 400);
        }

        if (!$payload instanceof \stdClass) {
            return Response::json([
                'error' => 'Invalid request payload.',
            ], 400);
        }

        $username = $payload->username ?? null;
        $password = $payload->password ?? null;

        if (
            !is_string($username)
            || !is_string($password)
            || trim($username) === ''
            || strlen($username) > 64
            || $password === ''
            || strlen($password) > 4096
        ) {
            return Response::json([
                'error' => 'Invalid login input.',
            ], 422);
        }

        if (!$this->authManager->login($username, $password)) {
            return Response::json([
                'error' => 'Invalid username or password.',
            ], 401);
        }

        $csrfToken = CsrfProtection::getToken();

        return Response::json(
            [
                'authenticated' => true,
                'csrf_token' => $csrfToken,
            ],
            200,
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
    }

    public function logout(
        Request $request,
        array $params
    ): Response {
        $this->authManager->logout();

        return Response::json(
            [
                'success' => true,
                'message' => 'Logout successful.',
            ],
            200,
            [
                'Cache-Control' => 'no-store',
            ]
        );
    }
}

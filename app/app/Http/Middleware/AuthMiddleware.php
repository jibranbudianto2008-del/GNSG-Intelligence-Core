<?php

declare(strict_types=1);

namespace GNSG\Http\Middleware;

use GNSG\Auth\AuthManager;
use GNSG\Http\Request;
use GNSG\Http\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthManager $auth
    ) {
    }

    public function process(
        Request $request,
        callable $next
    ): Response {
        $user = $this->auth->currentUser();

        if (!$this->isValidIdentity($user)) {
            return Response::json(
                [
                    'error' => [
                        'code' => 'authentication_required',
                        'message' => 'Authentication required.',
                    ],
                ],
                401
            );
        }

        return $next($request);
    }

    private function isValidIdentity(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        return
            array_key_exists('id', $user)
            && is_int($user['id'])
            && $user['id'] > 0
            && array_key_exists('username', $user)
            && is_string($user['username'])
            && trim($user['username']) !== ''
            && strlen($user['username']) <= 64
            && array_key_exists('email', $user)
            && (
                $user['email'] === null
                || (
                    is_string($user['email'])
                    && strlen($user['email']) <= 255
                )
            )
            && array_key_exists('display_name', $user)
            && is_string($user['display_name'])
            && strlen($user['display_name']) <= 120;
    }
}

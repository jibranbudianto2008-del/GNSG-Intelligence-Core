<?php

declare(strict_types=1);

namespace GNSG\Http\Middleware;

use GNSG\Auth\AuthManager;
use GNSG\Auth\AuthorizationManager;
use GNSG\Http\Request;
use GNSG\Http\Response;

final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly AuthorizationManager $authorization,
        private readonly string $permissionKey
    ) {
    }

    public function process(
        Request $request,
        callable $next
    ): Response {
        $user = $this->auth->currentUser();

        if (
            $user === null
            || !isset($user['id'])
            || !is_int($user['id'])
            || $user['id'] <= 0
        ) {
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

        if (
            !$this->authorization->hasPermission(
                $user['id'],
                $this->permissionKey
            )
        ) {
            return Response::json(
                [
                    'error' => [
                        'code' => 'permission_denied',
                        'message' => 'Permission denied.',
                    ],
                ],
                403
            );
        }

        return $next($request);
    }
}

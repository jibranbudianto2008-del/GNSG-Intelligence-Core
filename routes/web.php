<?php

declare(strict_types=1);

use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Router;
use GNSG\Auth\Session;
use GNSG\Security\CsrfProtection;
use GNSG\Auth\AuthManager;
use GNSG\Http\Controller\AuthController;

return static function (Router $router, ?AuthManager $authManager = null): void {
    $router->get(
        '/health',
        static function (
            Request $request,
            array $params
        ): Response {
            return Response::json([
                'status' => 'ok',
                'service' => 'GNSG Intelligence Core',
            ]);
        }
    );

    $router->get(
        '/dashboard/csrf-token',
        static function (
            Request $request,
            array $params
        ): Response {
            Session::start();

            $token = CsrfProtection::getToken();

            return Response::json(
                ['csrf_token' => $token],
                200,
                [
                    'Cache-Control' => 'no-store, no-cache, must-revalidate',
                    'Pragma' => 'no-cache',
                ]
            );
        }
    );

    $router->post(
        '/auth/login',
        static function (
            Request $request,
            array $params
        ) use ($authManager): Response {
            $controller = new AuthController(
                $authManager ?? new AuthManager()
            );

            return $controller->login($request, $params);
        }
    );

    $router->post(
        '/auth/logout',
        static function (
            Request $request,
            array $params
        ) use ($authManager): Response {
            $controller = new AuthController(
                $authManager ?? new AuthManager()
            );

            return $controller->logout($request, $params);
        }
    );

};

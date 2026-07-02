<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Square sends webhooks as an unauthenticated external server, not
        // a browser session — CSRF protection isn't applicable, and the
        // request is verified instead via its HMAC signature (see
        // SquareWebhookController).
        $middleware->preventRequestForgery(except: ['webhooks/square']);

        // Named limiter defined in AppServiceProvider::boot() — keyed by
        // bearer token (falling back to IP) rather than the authenticated
        // user, so it applies even to invalid-token brute-force attempts.
        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Every /api/v1/* error response — however it originates — shares
        // the same {success, message, data} envelope as our own
        // FormRequest/controller responses (see ApiFormRequest, ApiResponses).
        //
        // Note: Illuminate\Auth\Access\AuthorizationException (thrown by
        // $this->authorize() in controllers) never reaches render() as
        // itself — Handler::prepareException() always converts it to
        // AccessDeniedHttpException first, so that's what we type-hint here.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'This action is unauthorized.',
                    'data' => null,
                ], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'data' => null,
                ], 404);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                    'data' => null,
                ], 429);
            }
        });

        // Backstop for anything else — never let a raw exception message or
        // stack trace reach an API consumer.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong. Please try again later.',
                    'data' => null,
                ], 500);
            }
        });
    })->create();

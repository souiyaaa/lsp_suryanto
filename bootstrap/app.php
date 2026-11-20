<?php

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Web requests fall through to resources/views/errors/{404,500}.blade.php.
        // API requests get the same JSON envelope as ApiResponse.
        $isApi = fn (Request $request) => $request->is('api/*');

        $exceptions->render(function (ValidationException $validationException, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return Controller::errorResponse('Validation failed', 422, $validationException->errors());
        });

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $notFoundException, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return Controller::errorResponse('Resource not found', 404);
        });

        $exceptions->render(function (Throwable $throwable, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            // Keep real HTTP codes (405, 403...) instead of masking them as 500.
            if ($throwable instanceof HttpExceptionInterface) {
                return Controller::errorResponse($throwable->getMessage() ?: 'Request error', $throwable->getStatusCode());
            }

            // Show the real cause only while debugging; never leak it in production.
            $message = config('app.debug') ? $throwable->getMessage() : 'Internal server error';

            return Controller::errorResponse($message, 500);
        });
    })->create();
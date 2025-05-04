<?php

declare(strict_types=1);

namespace App\Exceptions;

use BadMethodCallException;
use DomainException;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [

    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // return response()->json(['error' => '3'], Response::HTTP_BAD_REQUEST);

        // $this->renderable(function (Exception $e, $request) {
        //     if ($e instanceof \DomainException && $request->expectsJson()) {
        //         return response()->json([
        //             'error' => $e->getMessage(),
        //         ], Response::HTTP_BAD_REQUEST);
        //     }
        // });

        // https://laraveldaily.com/post/laravel-api-override-404-error-message-route-model-binding
        // $this->renderable(function (NotFoundHttpException $e, $request) {
        //     if ($request->is('api/*')) { // <- Add your condition here
        //         return response()->json([
        //             'error' => 'Resource not found.'
        //         ], Response::HTTP_NOT_FOUND);
        //     }
        // });

        $this->renderable(function (Exception $e, $request) {
            if ($e instanceof NotFoundHttpException && $request->expectsJson()) {
                return response()->json([
                    'error' => 'Resource not found.',
                ], Response::HTTP_NOT_FOUND);
            }
        });

        $this->renderable(function (Exception $e, $request) {
            if ($e instanceof AccessDeniedHttpException && $request->expectsJson()) {
                return response()->json([
                    'error' => 'Insufficient rights to a resource.',
                ], Response::HTTP_FORBIDDEN);
            }
        });

        $this->renderable(function (Exception $e, $request) {
            if (($e instanceof DomainException || $e instanceof BadMethodCallException) && $request->expectsJson()) {
                return response()->json([
                    'error' => $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        });
    }

    /**
     * changed on 24.06.2024
     */
    public function render($request, $exception)
    {
        if ($exception instanceof DomainException) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return parent::render($request, $exception);
    }

    // // https://www.toptal.com/laravel/restful-laravel-api-tutorial
    // public function render($request, $exception)
    // {
    //     // This will replace our 404 response with a JSON response.
    //     if ($exception instanceof ModelNotFoundException && $request->wantsJson()) {
    //         return response()->json([
    //             'error' => 'Resource not found'
    //         ], Response::HTTP_NOT_FOUND);
    //     }

    //     // This will replace our 403 response with a JSON response.
    //     if ($exception instanceof AccessDeniedHttpException && $request->wantsJson()) {
    //         return response()->json([
    //             'error' => 'Insufficient rights to a resource'
    //         ], Response::HTTP_FORBIDDEN);
    //     }

    //     return parent::render($request, $exception);
    // }

    // public function report(Exception $exception)
    // {
    //     parent::report($exception);
    // }

    // public function render(Request $request, Exception $exception)  // todo: how to test it (L11 04:47:40)
    // {
    //     if ($exception instanceof \DomainException && $request->expectsJson()) {
    //         return response()->json([
    //             'error' => $exception->getMessage(),
    //         ], Response::HTTP_BAD_REQUEST);
    //     }

    //     return parent::render($request, $exception);
    // }
}

<?php
declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
        });
    }

    /**
     * > If the request is an AJAX request, return a JSON response with the errors
     *
     * @param \Illuminate\Http\Request $request The incoming request.
     * @param \Illuminate\Validation\ValidationException $exception The exception instance.
     * @return \Illuminate\Http\JsonResponse A JSON response with the errors.
     */
    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        $messages = [];

        foreach ($exception->errors() as $key => $value) {
            $item['field'] = $key;
            $item['message'] = head($value);

            $messages[] = $item;
        }

        return response()->json([
            'type' => 'validation_error',
            'errors' => $messages,
        ], $exception->status);
    }

    /**
     * @inheritDoc
     */
    public function render($request, Throwable $e): Response|JsonResponse|HttpResponse
    {
        if ($request->wantsJson()) {
            return match (true) {
                $e instanceof ModelNotFoundException,
                    $e instanceof NotFoundHttpException,
                => response()->json([
                    'type' => 'not_found',
                ], 404),
                $e instanceof AuthorizationException => response()->json([
                    'type' => 'authorization_error',
                ], 401),
                $e instanceof AuthenticationException => response()->json([
                    'type' => 'authentication_error',
                ], 403),
                $e instanceof BadRequestException => response()->json([
                    'type' => 'bad_request',
                ], 400),
                $e instanceof ValidationException => $this->convertValidationExceptionToResponse($e, $request),
                default => response()->json([
                    'type' => method_exists($e, 'getErrorType') ? $e->getErrorType() : 'server_error',
                ], $this->validHttpStatus($e)),
            };
        } else {
            return parent::render($request, $e);
        }
    }

    /**
     * @param \Throwable $e
     * @return int
     */
    private function validHttpStatus(Throwable $e): int
    {
        return array_key_exists($e->getCode(), Response::$statusTexts) ? $e->getCode() : 500;
    }
}

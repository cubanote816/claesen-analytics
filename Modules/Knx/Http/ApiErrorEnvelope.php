<?php

namespace Modules\Knx\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Exceptions\KnxApiException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The uniform error body of docs/BACKEND-API.md §2.5.
 *
 * The office app turns any non-2xx into an `ApiError` with `status`, `code`,
 * `message` and `details`, so returning this shape is what gives it correct
 * messages for free. Laravel's own responses only carry `message` (validation
 * adds `errors`), which is why `code` has to be added here.
 *
 * Scoped to `/api/v1/knx/*` on purpose: this is the only surface in the app
 * that promises this contract, and rewriting errors globally would change
 * behaviour for Claesen's existing APIs and the Filament panels.
 */
final class ApiErrorEnvelope
{
    public const CODE_BAD_REQUEST = 'bad_request';

    public const CODE_UNAUTHENTICATED = 'unauthenticated';

    public const CODE_FORBIDDEN = 'forbidden';

    public const CODE_NOT_FOUND = 'not_found';

    public const CODE_CONFLICT = 'conflict';

    public const CODE_VALIDATION = 'validation_error';

    public const CODE_RATE_LIMITED = 'rate_limited';

    public const CODE_SERVER_ERROR = 'server_error';

    /** Codes by HTTP status, for exceptions that only carry a status. */
    private const BY_STATUS = [
        Response::HTTP_BAD_REQUEST => self::CODE_BAD_REQUEST,
        Response::HTTP_UNAUTHORIZED => self::CODE_UNAUTHENTICATED,
        Response::HTTP_FORBIDDEN => self::CODE_FORBIDDEN,
        Response::HTTP_NOT_FOUND => self::CODE_NOT_FOUND,
        Response::HTTP_CONFLICT => self::CODE_CONFLICT,
        Response::HTTP_UNPROCESSABLE_ENTITY => self::CODE_VALIDATION,
        Response::HTTP_TOO_MANY_REQUESTS => self::CODE_RATE_LIMITED,
    ];

    public static function handles(Request $request): bool
    {
        return $request->is('api/v1/knx/*');
    }

    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! self::handles($request)) {
            return null;
        }

        [$status, $code, $errors] = self::classify($exception);

        return response()->json([
            'message' => self::message($exception, $status),
            'code' => $code,
            // Only the validation shape carries field errors; the key is always
            // present so the front never has to branch on its existence.
            'errors' => (object) $errors,
        ], $status);
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, array<int, string>>}
     */
    private static function classify(Throwable $exception): array
    {
        // Our own exceptions carry their contract code explicitly.
        if ($exception instanceof KnxApiException) {
            return [$exception->status(), $exception->errorCode(), $exception->errors()];
        }

        if ($exception instanceof ValidationException) {
            return [Response::HTTP_UNPROCESSABLE_ENTITY, self::CODE_VALIDATION, $exception->errors()];
        }

        if ($exception instanceof AuthenticationException) {
            return [Response::HTTP_UNAUTHORIZED, self::CODE_UNAUTHENTICATED, []];
        }

        if ($exception instanceof AuthorizationException) {
            return [Response::HTTP_FORBIDDEN, self::CODE_FORBIDDEN, []];
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return [Response::HTTP_NOT_FOUND, self::CODE_NOT_FOUND, []];
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            // 429 arrives as a plain HttpException from the throttle middleware.
            return [$status, self::BY_STATUS[$status] ?? self::CODE_SERVER_ERROR, []];
        }

        return [Response::HTTP_INTERNAL_SERVER_ERROR, self::CODE_SERVER_ERROR, []];
    }

    private static function message(Throwable $exception, int $status): string
    {
        // A 5xx must never leak internals, whatever APP_DEBUG says: this
        // response is built by hand instead of letting Laravel add the
        // debug-only `exception`/`file`/`line`/`trace` keys.
        if ($status >= 500) {
            return 'Something went wrong.';
        }

        $message = trim($exception->getMessage());

        if ($message !== '') {
            return $message;
        }

        return match ($status) {
            Response::HTTP_UNAUTHORIZED => 'Unauthenticated.',
            Response::HTTP_FORBIDDEN => 'This action is unauthorized.',
            Response::HTTP_NOT_FOUND => 'Not found.',
            default => 'The given data was invalid.',
        };
    }
}

<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConfigureApiExceptions
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => self::wantsApiResponse($request),
        );

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! self::wantsApiResponse($request)) {
                return null;
            }

            return response()->json(['message' => self::notFoundMessage($e)], 404);
        });

        $exceptions->render(function (TicketNotFoundException $e, Request $request) {
            if (! self::wantsApiResponse($request)) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], 404);
        });

        $exceptions->render(function (TicketAlreadyEscalatedException $e, Request $request) {
            if (! self::wantsApiResponse($request)) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], 409);
        });

        $exceptions->render(function (UnsupportedEscalationChannelException $e, Request $request) {
            if (! self::wantsApiResponse($request)) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        });
    }

    public static function notFoundMessage(NotFoundHttpException $e): string
    {
        $previous = $e->getPrevious();

        if (! $previous instanceof ModelNotFoundException) {
            return 'Not found.';
        }

        $model = class_basename((string) $previous->getModel());
        $ids = implode(', ', $previous->getIds());

        if ($model === 'Ticket') {
            return $ids === ''
                ? 'Ticket was not found.'
                : "Ticket [{$ids}] was not found.";
        }

        return $ids === ''
            ? "{$model} was not found."
            : "{$model} [{$ids}] was not found.";
    }

    private static function wantsApiResponse(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}

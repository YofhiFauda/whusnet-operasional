<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Kegagalan domain endpoint #2 (`POST /api/v1/installations/network-assignment`,
 * docs/api/api-pop-distribusi/business-logic.md). Extends HttpException supaya
 * `bootstrap/app.php`'s `shouldRenderJsonWhen()` merender ini sebagai JSON
 * `{"message": "..."}` dengan status code yang benar tanpa controller perlu
 * membangun response()->json() manual di tiap titik gagal.
 */
class NetworkAssignmentException extends HttpException
{
    public static function notFound(string $message): self
    {
        return new self(404, $message);
    }

    public static function unprocessable(string $message): self
    {
        return new self(422, $message);
    }
}

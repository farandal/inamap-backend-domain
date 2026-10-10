<?php

namespace Domain\App\Services\Inamap\Routing;

use RuntimeException;

/**
 * Error tipificado del motor de rutas (README §9.2, contrato de error).
 */
class RouteException extends RuntimeException
{
    public const UNREACHABLE            = 'UNREACHABLE';
    public const UNREACHABLE_ACCESSIBLE = 'UNREACHABLE_ACCESSIBLE';
    public const NO_PATH_NEAR_ENDPOINT  = 'NO_PATH_NEAR_ENDPOINT';
    public const ENDPOINT_NOT_FOUND     = 'ENDPOINT_NOT_FOUND';

    public function __construct(public readonly string $errorCode, string $message = '')
    {
        parent::__construct($message ?: $errorCode);
    }

    public function httpStatus(): int
    {
        return match ($this->errorCode) {
            self::NO_PATH_NEAR_ENDPOINT, self::ENDPOINT_NOT_FOUND => 422,
            default => 404,
        };
    }
}

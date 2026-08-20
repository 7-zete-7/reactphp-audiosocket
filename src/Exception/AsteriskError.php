<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Exception;

use Zete7\AudioSocket\Protocol\ErrorMessage;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class AsteriskError extends \RuntimeException implements ExceptionInterface
{
    public function __construct(
        public readonly string $asteriskErrorCode,

        string $message,

        int $code = 0,

        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function createFromErrorMessage(ErrorMessage $message): self
    {
        return new self(
            asteriskErrorCode: $message->payload,
            message: \sprintf('Asterisk error 0x%s.', bin2hex($message->payload)),
        );
    }
}

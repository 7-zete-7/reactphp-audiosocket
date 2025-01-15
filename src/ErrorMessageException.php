<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Zete7\React\AudioSocket\Protocol\ErrorCode;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
class ErrorMessageException extends \RuntimeException
{
    public function __construct(string $message, ErrorCode $errorCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, $errorCode->value, $previous);
    }
}

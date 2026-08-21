<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Exception;

use Zete7\AudioSocket\Protocol\Message;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class UnexpectedMessageError extends \UnexpectedValueException implements ExceptionInterface
{
    public function __construct(
        public readonly Message $receivedMessage,

        string $message = '',

        int $code = 0,

        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function createFromMessage(Message $receivedMessage): self
    {
        return new self(
            receivedMessage: $receivedMessage,
            message: \sprintf('Unexpected message kind "%s" received.', $receivedMessage->kind->name),
        );
    }
}

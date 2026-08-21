<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Zete7\AudioSocket\Protocol\AudioMessage;
use Zete7\AudioSocket\Protocol\DtmfMessage;
use Zete7\AudioSocket\Protocol\DtmfSignal;
use Zete7\AudioSocket\Protocol\ErrorMessage;
use Zete7\AudioSocket\Protocol\HangupMessage;
use Zete7\AudioSocket\Protocol\Message;
use Zete7\AudioSocket\Protocol\UuidMessage;
use Zete7\React\AudioSocket\Exception\UnexpectedMessageError;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class ClientConnection extends AbstractConnection implements ClientConnectionInterface
{
    #[\Override]
    public function sendDtmf(DtmfSignal $signal): void
    {
        if (!$this->writable) {
            return;
        }

        $message = new DtmfMessage(
            signal: $signal,
        );

        $bytes = $this->messageEncoder->encodeMessage($message);
        $this->connection->write($bytes);
    }

    #[\Override]
    public function sendError(string $payload): void
    {
        if (!$this->writable) {
            return;
        }

        $message = new ErrorMessage(
            payload: $payload,
        );

        $bytes = $this->messageEncoder->encodeMessage($message);
        $this->connection->write($bytes);
    }

    #[\Override]
    protected function processMessage(Message $message): void
    {
        if ($message instanceof AudioMessage) {
            $this->emit('audio', [$message->payload, $message->audioFormat]);

            return;
        }

        if ($message instanceof HangupMessage) {
            $this->emit('hangup');
            $this->close();

            // @infection-ignore-all optimization
            return;
        }

        $this->fatalError(UnexpectedMessageError::createFromMessage($message));
    }

    #[\Override]
    protected function initializeConnection(): void
    {
        $initialMessage = new UuidMessage(
            uuid: $this->uuid,
        );

        $bytes = $this->messageEncoder->encodeMessage($initialMessage);
        $this->connection->write($bytes);
    }
}

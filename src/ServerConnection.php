<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket;

use Zete7\AudioSocket\Protocol\AudioMessage;
use Zete7\AudioSocket\Protocol\DtmfMessage;
use Zete7\AudioSocket\Protocol\HangupMessage;
use Zete7\AudioSocket\Protocol\Message;
use Zete7\React\AudioSocket\Exception\UnexpectedMessageError;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class ServerConnection extends AbstractConnection implements ServerConnectionInterface
{
    #[\Override]
    public function sendHangup(): void
    {
        if (!$this->writable) {
            return;
        }

        $this->writable = false;

        $message = new HangupMessage();
        $bytes = $this->messageEncoder->encodeMessage($message);
        $this->connection->end($bytes);
    }

    #[\Override]
    protected function processMessage(Message $message): void
    {
        match (true) {
            $message instanceof AudioMessage => $this->emit('audio', [$message->payload, $message->audioFormat]),
            $message instanceof DtmfMessage => $this->emit('dtmf', [$message->signal]),
            default => $this->fatalError(UnexpectedMessageError::createFromMessage($message)),
        };
    }
}

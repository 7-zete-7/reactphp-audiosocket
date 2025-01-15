<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Protocol;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
class Parser
{
    private string $buffer = '';

    /**
     * @return Message[]
     */
    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;

        /** @var Message[] $messages */
        $messages = [];

        while (false !== $parts = @unpack('ckind/ncontent_length', $this->buffer)) {
            $contentLength = $parts['content_length'];
            $messageLength = $contentLength + 3;

            if (strlen($this->buffer) < $messageLength) {
                break;
            }

            $message = substr($this->buffer, 0, $messageLength);
            $this->buffer = (string) substr($this->buffer, $messageLength);

            $messages[] = new Message($message);
        }

        return $messages;
    }
}

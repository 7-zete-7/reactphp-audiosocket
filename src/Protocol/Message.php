<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Protocol;

use Symfony\Component\Uid\Uuid;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
class Message
{
    public function __construct(
        private readonly string $raw,
    ) {
    }

    public static function createHangupMessage(): self
    {
        return new self(pack('cn', Kind::Hangup->value, 0));
    }

    public static function createIdMessage(Uuid $id): self
    {
        return new self(pack('cn', Kind::ID->value, 16).$id->toBinary());
    }

    public static function createSilenceMessage(): self
    {
        return new self(pack('cn', Kind::Silence->value, 0));
    }

    public static function createSlinMessage(string $slin): self
    {
        if (strlen($slin) > 65535) {
            throw new TooLargeMessageException('Message is too large');
        }

        return new self(pack('cn', Kind::Slin->value, strlen($slin)).$slin);
    }

    public static function createErrorCodeMessage(ErrorCode $errorCode): self
    {
        return new self(\pack('cnc', Kind::Error->value, 1, $errorCode->value));
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    /**
     * Returns the length of the payload of the message
     */
    public function getContentLength(): int
    {
        if (strlen($this->raw) < 3) {
            return 0;
        }

        $parts = unpack('ckind/ncontent_length', $this->raw);

        return $parts['content_length'];
    }

    /**
     * Returns the type of the message
     */
    public function getKind(): Kind
    {
        if (strlen($this->raw) < 1) {
            return Kind::Error;
        }

        $parts = unpack('ckind', $this->raw);

        return Kind::from($parts['kind']);
    }

    /**
     * Returns the coded error of the message, if present
     */
    public function getErrorCode(): ErrorCode
    {
        if ($this->getKind() !== Kind::Error) {
            return ErrorCode::None;
        }

        if (strlen($this->raw) < 4) {
            return ErrorCode::Unknown;
        }

        $parts = unpack('ckind/ncontent_length/cerror_code', $this->raw);

        return ErrorCode::from($parts['error_code']);
    }

    /**
     * Payload returns the data of the payload of the message
     */
    public function getPayload(): string
    {
        if (0 === $contentLength = $this->getContentLength()) {
            return '';
        }

        return substr($this->raw, 3);
    }

    /**
     * Returns the session's unique ID if and only if the Message is the initial ID message
     */
    public function getId(): Uuid
    {
        if (Kind::ID !== $this->getKind()) {
            throw new WrongMessageTypeException(\sprintf('Wrong message type %x', $this->getKind()));
        }

        return Uuid::fromBinary($this->getPayload());
    }
}

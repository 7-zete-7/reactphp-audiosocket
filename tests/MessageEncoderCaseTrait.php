<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zete7\AudioSocket\MessageEncoder;

/**
 * @require-extends TestCase
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
trait MessageEncoderCaseTrait
{
    private MessageEncoder $messageEncoder {
        get => $this->messageEncoder ??= self::createStub(MessageEncoder::class);
    }

    private function requireMessageEncoderMock(): MessageEncoder&MockObject
    {
        return $this->messageEncoder = $this->createMock(MessageEncoder::class);
    }
}

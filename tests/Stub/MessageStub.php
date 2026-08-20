<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test\Stub;

use Zete7\AudioSocket\Protocol\Kind;
use Zete7\AudioSocket\Protocol\Message;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final readonly class MessageStub implements Message
{
    public function __construct(
        public Kind $kind,

        public string $payload,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\Socket\ConnectionInterface as ReactConnectionInterface;
use Zete7\React\AudioSocket\Test\Stub\ReactConnectionStub;

/**
 * @require-extends TestCase
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
trait ReactConnectionCaseTrait
{
    private bool $reactConnectionReadable = true;

    private bool $reactConnectionWritable = true;

    private ?string $reactConnectionRemoteAddress = null;

    private ?string $reactConnectionLocalAddress = null;

    private ReactConnectionInterface $reactConnection {
        get => $this->reactConnection ??= self::createConfiguredStub(ReactConnectionInterface::class, [
            'isReadable' => $this->reactConnectionReadable,
            'isWritable' => $this->reactConnectionWritable,
            'getRemoteAddress' => $this->reactConnectionRemoteAddress,
            'getLocalAddress' => $this->reactConnectionLocalAddress,
        ]);
    }

    private function requireReactConnectionMock(): ReactConnectionInterface&MockObject
    {
        return $this->reactConnection = self::createConfiguredMock(ReactConnectionInterface::class, [
            'isReadable' => $this->reactConnectionReadable,
            'isWritable' => $this->reactConnectionWritable,
            'getRemoteAddress' => $this->reactConnectionRemoteAddress,
            'getLocalAddress' => $this->reactConnectionLocalAddress,
        ]);
    }

    private function requireReactConnectionStub(): ReactConnectionStub
    {
        return $this->reactConnection = new ReactConnectionStub(
            remoteAddress: $this->reactConnectionRemoteAddress,
            localAddress: $this->reactConnectionLocalAddress,
        );
    }
}

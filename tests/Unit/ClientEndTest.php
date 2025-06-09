<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;
use React\Socket\ConnectionInterface;
use Zete7\React\AudioSocket\Client;
use Zete7\React\AudioSocket\Protocol\Parser;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
#[PHPUnit\CoversMethod(Client::class, 'end')]
final class ClientEndTest extends TestCase
{
    #[PHPUnit\Test]
    public function testSuccessHangupMessageSend(): void
    {
        $stream = $this->createMock(ConnectionInterface::class);
        $parser = $this->createStub(Parser::class);

        $client = new Client($stream, $parser);

        $stream
            ->expects($this->once())
            ->method('pause')
        ;

        $stream
            ->expects($this->once())
            ->method('write')
            ->with(b"\x00\x00\x00")
            ->willReturn(true)
        ;

        $client->on('close', $this->assertNeverBeCalled());
        $client->on('error', $this->assertNeverBeCalled());
        $client->on('data', $this->assertNeverBeCalled());

        $client->end();
        $client->end();
        $client->end();
    }

    #[PHPUnit\Test]
    public function testErrorHangupFailedSend(): void
    {
        $stream = $this->createMock(ConnectionInterface::class);
        $parser = $this->createStub(Parser::class);

        $client = new Client($stream, $parser);

        $stream
            ->expects($this->once())
            ->method('pause');

        $stream
            ->expects($this->once())
            ->method('write')
            ->with(b"\x00\x00\x00")
            ->willReturn(false);

        $client->on('close', $this->assertOnceBeCalled()); // after "error" event
        $client->on('error', $this->assertOnceBeCalled());
        $client->on('data', $this->assertNeverBeCalled());

        $client->end();
        $client->end();
        $client->end();
    }

    private function assertNeverBeCalled(): callable
    {
        return static function (): void {
            throw new AssertionFailedError('Failed asserting that function will never called');
        };
    }

    private function assertOnceBeCalled(): callable
    {
        $callsCount = 0;

        return static function () use (&$callsCount): void {
            match ($callsCount) {
                0 => ++$callsCount,
                default => throw new AssertionFailedError('Failed asserting that function will once called')
            };
        };
    }
}

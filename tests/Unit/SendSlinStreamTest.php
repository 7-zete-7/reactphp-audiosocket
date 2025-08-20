<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Zete7\React\AudioSocket\Client;
use Zete7\React\AudioSocket\SendSlinStream;
use React\Async;
use React\Promise;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
#[PHPUnit\CoversClass(SendSlinStream::class)]
final class SendSlinStreamTest extends TestCase
{
    #[PHPUnit\Test]
    #[PHPUnit\Ticket('https://github.com/7-zete-7/reactphp-audiosocket/issues/5')]
    public function testStreamCloseOnDrainWhenTimerTick(): void
    {
        $client = $this->createMock(Client::class);

        $stream = new SendSlinStream(
            stream: $client,
            chunkDuration: 0.001, // 16B per chunk
            softLimit: 16, // 16B
        );

        $client->method('send')->willReturn(true);

        $deferred = new Deferred();

        $stream->on('drain', static function () use ($deferred): void {
            $deferred->resolve(null);
        });

        $unexceeded = $stream->write('4fd21b0af74b4d6dbd19'); // 20B

        $this->assertFalse($unexceeded);

        Async\await(Promise\race([
            $deferred->promise(),
            $this->delayRejection(1, new \RuntimeException('Timeout')),
        ]));

        Async\delay(0.005);

        $stream->close();
    }

    private function delayRejection(float $seconds, \Throwable $reason): PromiseInterface
    {
        $deferred = new Deferred();

        Loop::addTimer($seconds, static function () use ($deferred, $reason): void {
            $deferred->reject($reason);
        });

        return $deferred->promise();
    }
}

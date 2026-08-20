<?php

declare(strict_types=1);

namespace Zete7\React\AudioSocket\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\Socket\ConnectionInterface as ReactConnectionInterface;
use React\Socket\ServerInterface as ReactServerInterface;
use Symfony\Component\Uid\Uuid;
use Zete7\AudioSocket\MessageEncoder;
use Zete7\AudioSocket\Protocol\DtmfMessage;
use Zete7\AudioSocket\Protocol\DtmfSignal;
use Zete7\AudioSocket\Protocol\UuidMessage;
use Zete7\React\AudioSocket\ConnectionInterface;
use Zete7\React\AudioSocket\Exception\UninitializedConnectionError;
use Zete7\React\AudioSocket\Server;
use Zete7\React\AudioSocket\Test\Stub\ReactConnectionStub;
use Zete7\React\AudioSocket\Test\Stub\ReactServerStub;

/**
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
#[PHPUnit\CoversClass(Server::class)]
final class ServerTest extends TestCase
{
    private ?string $reactServerAddress = null;

    private ReactServerInterface $reactServer {
        get => $this->reactServer ??= self::createConfiguredStub(ReactServerInterface::class, [
            'getAddress' => $this->reactServerAddress,
        ]);
    }

    private MessageEncoder $messageEncoder {
        get => $this->messageEncoder ??= self::createStub(MessageEncoder::class);
    }

    private Server $server {
        get => $this->server ??= (function (): Server {
            $server = new Server(
                server: $this->reactServer,
                messageEncoder: $this->messageEncoder,
            );

            $this->configureServer($server);

            return $server;
        })();

        set(Server $server) {
            $this->server = $server;
            $this->configureServer($server);
        }
    }

    /**
     * @var list<ConnectionInterface>
     */
    private array $serverConnectionEvents = [];

    /**
     * @var list<\Throwable>
     */
    private array $serverErrorEvents = [];

    #[PHPUnit\Test]
    public function testServerInitialState(): void
    {
        $this->assertServerIsNotClosed();
    }

    #[PHPUnit\Test]
    public function testServerAddressForwarding(): void
    {
        $this->reactServerAddress = 'foo';

        $this->assertServerIsNotClosed();
        $this->assertServerAddressIs('foo');
    }

    #[PHPUnit\Test]
    public function testServerPauseForwarding(): void
    {
        $reactServer = $this->requireReactServerMock();

        $this->assertServerIsNotClosed();

        $reactServer
            ->expects(self::once())
            ->method('pause')
        ;

        $this->server->pause();
    }

    #[PHPUnit\Test]
    public function testServerResumeForwarding(): void
    {
        $reactServer = $this->requireReactServerMock();

        $this->assertServerIsNotClosed();

        $reactServer
            ->expects(self::once())
            ->method('resume')
        ;

        $this->server->resume();
    }

    #[PHPUnit\Test]
    public function testServerCloseForwarding(): void
    {
        $reactServer = $this->requireReactServerMock();

        $this->assertServerIsNotClosed();

        $reactServer
            ->expects(self::once())
            ->method('close')
        ;

        $this->server->close();

        $this->assertServerIsClosed();
    }

    #[PHPUnit\Test]
    public function testServerErrorEventForwarding(): void
    {
        $reactServer = $this->requireReactServerStub();

        $this->assertServerIsNotClosed();
        $this->assertServerErrorEventsAreEmpty();

        $error = new \LogicException('foo');
        $reactServer->emitError($error);

        $this->assertServerErrorEventsAreEquals([$error]);
    }

    #[PHPUnit\Test]
    public function testConnectionEarlyClosed(): void
    {
        $reactServer = $this->requireReactServerStub();

        $this->assertServerIsNotClosed();

        $reactConnection = self::createConfiguredStub(ReactConnectionInterface::class, [
            'isReadable' => false,
            'isWritable' => false,
        ]);

        $this->assertServerErrorEventsAreEmpty();

        $reactServer->emitConnection($reactConnection);

        $this->assertServerErrorEventsAreEquals([
            new UninitializedConnectionError('Unable to receive AudioSocket connection UUID: Stream already closed', 0, new \RuntimeException('Stream already closed')),
        ]);
        $this->assertServerConnectionEventsAreEmpty();
    }

    #[PHPUnit\Test]
    public function testConnectionNotStringData(): void
    {
        $reactServer = $this->requireReactServerStub();

        $this->assertServerIsNotClosed();

        $reactConnection = new ReactConnectionStub();
        $reactServer->emitConnection($reactConnection);

        self::expectException(\AssertionError::class);
        self::expectExceptionMessageIs('assert(\is_string($data))');

        $reactConnection->write(12);

        $this->assertServerErrorEventsAreEmpty();
        $this->assertServerConnectionEventsAreEmpty();
    }

    #[PHPUnit\Test]
    public function testConnectionEmptyData(): void
    {
        $reactServer = $this->requireReactServerStub();

        $this->assertServerIsNotClosed();

        $reactConnection = new ReactConnectionStub();
        $reactServer->emitConnection($reactConnection);

        $this->assertServerErrorEventsAreEmpty();

        $reactConnection->close();
        $reactConnection->emitClose();

        $this->assertServerErrorEventsAreEquals([
            new UninitializedConnectionError('Unable to receive AudioSocket connection UUID: Stream closed', 0, new \RuntimeException('Stream closed')),
        ]);
        $this->assertServerConnectionEventsAreEmpty();
    }

    #[PHPUnit\Test]
    public function testConnectionTooSmallData(): void
    {
        $reactServer = $this->requireReactServerStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertServerIsNotClosed();

        $reactConnection = new ReactConnectionStub();
        $reactServer->emitConnection($reactConnection);

        $this->assertServerErrorEventsAreEmpty();

        $messageEncoder
            ->expects(self::once())
            ->method('decodeMessage')
            ->willReturn(null)
        ;

        $reactConnection->emitData('12');

        $this->assertServerErrorEventsAreEquals([
            new UninitializedConnectionError('AudioSocket connection has no message in first received data.'),
        ]);
        $this->assertServerConnectionEventsAreEmpty();
    }

    #[PHPUnit\Test]
    public function testConnectionNonUuidData(): void
    {
        $reactServer = $this->requireReactServerStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertServerIsNotClosed();

        $reactConnection = new ReactConnectionStub();
        $reactServer->emitConnection($reactConnection);

        $this->assertServerErrorEventsAreEmpty();

        $initialData = "\x29\x34\x52\xB0\x90\xB1\x48\x54\xBF\x4A\x59\xA8\xC0\x71\x03\x34";

        $messageEncoder
            ->expects(self::once())
            ->method('decodeMessage')
            ->with(self::identicalTo($initialData))
            ->willReturn(new DtmfMessage(DtmfSignal::A))
        ;

        $reactConnection->emitData($initialData);

        $this->assertServerErrorEventsAreEquals([
            new UninitializedConnectionError('AudioSocket connection was initialized with non-UUID message (0x03).'),
        ]);
        $this->assertServerConnectionEventsAreEmpty();
    }

    #[PHPUnit\Test]
    public function testValidConnection(): void
    {
        $reactServer = $this->requireReactServerStub();
        $messageEncoder = $this->requireMessageEncoderMock();

        $this->assertServerIsNotClosed();

        $reactConnection = new ReactConnectionStub();
        $reactServer->emitConnection($reactConnection);

        $this->assertServerConnectionEventsAreEmpty();

        $initialData = "\x27\x85\x0A\x77\x00\x4F\x4E\x2C\xB5\xEE\x07\x44\x8E\x15\xF0\x7E";
        $uuid = Uuid::fromString('e8d767e7-816b-4d17-aa5d-39fa255217cb');

        $messageEncoder
            ->expects(self::once())
            ->method('decodeMessage')
            ->with(self::identicalTo($initialData))
            ->willReturn(new UuidMessage($uuid))
        ;

        $reactConnection->emitData($initialData);

        $this->assertServerErrorEventsAreEmpty();
        $this->assertServerConnectionEventsAreEqualUuids([$uuid]);
    }

    private function requireReactServerMock(): ReactServerInterface&MockObject
    {
        return $this->reactServer = $this->createConfiguredMock(ReactServerInterface::class, [
            'getAddress' => $this->reactServerAddress,
        ]);
    }

    private function requireReactServerStub(): ReactServerStub
    {
        return $this->reactServer = new ReactServerStub(
            address: $this->reactServerAddress,
        );
    }

    private function requireMessageEncoderMock(): MessageEncoder&MockObject
    {
        return $this->messageEncoder = $this->createMock(MessageEncoder::class);
    }

    private function configureServer(Server $server): void
    {
        $server->on('connection', function (ConnectionInterface $connection): void {
            $this->serverConnectionEvents[] = $connection;
        });

        $server->on('error', function (\Throwable $error): void {
            $this->serverErrorEvents[] = $error;
        });
    }

    private function assertServerIsClosed(string $message = ''): void
    {
        self::assertTrue($this->server->isClosed(), $message);
    }

    private function assertServerIsNotClosed(string $message = ''): void
    {
        self::assertFalse($this->server->isClosed(), $message);
    }

    private function assertServerAddressIs(?string $expected, string $message = ''): void
    {
        self::assertSame($expected, $this->server->getAddress(), $message);
    }

    private function assertServerConnectionEventsAreEmpty(string $message = ''): void
    {
        self::assertEmpty($this->serverConnectionEvents, $message);
    }

    /**
     * @param list<Uuid> $uuids
     */
    private function assertServerConnectionEventsAreEqualUuids(array $uuids, string $message = ''): void
    {
        self::assertEquals($uuids, array_map(static fn (ConnectionInterface $connection): Uuid => $connection->getUuid(), $this->serverConnectionEvents), $message);
    }

    private function assertServerErrorEventsAreEmpty(string $message = ''): void
    {
        self::assertEmpty($this->serverErrorEvents, $message);
    }

    /**
     * @param list<\Throwable> $expected
     */
    private function assertServerErrorEventsAreEquals(array $expected, string $message = ''): void
    {
        self::assertEquals($expected, $this->serverErrorEvents, $message);
    }
}

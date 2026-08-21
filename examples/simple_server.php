<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\AudioSocket\Protocol\DtmfSignal;
use Zete7\React\AudioSocket\AudioStream\AudioStreamInterface;
use Zete7\React\AudioSocket\Server;
use Zete7\React\AudioSocket\ServerConnectionInterface;

require_once dirname(__DIR__).'/vendor/autoload.php';

$server = new Server(new SocketServer('tcp://127.0.0.1:9092', [
    'tcp' => [
        'tcp_nodelay' => true,
    ],
]));

$server->on('close', static function (): void {
    echo "AudioSocket server was closed.\n";
});

$server->on('error', static function (Throwable $error): void {
    printf("AudioSocket server error: %s\n", $error);
});

$server->on('connection', static function (ServerConnectionInterface $connection): void {
    $remoteAddress = $connection->getRemoteAddress() ?? 'unknown';
    $uuid = $connection->getUuid();

    printf("Acquired AudioSocket connection %s from %s.\n", $uuid, $remoteAddress);

    $connection->on('close', static function () use ($uuid): void {
        printf("AudioSocket connection %s was closed.\n", $uuid->toRfc4122());
    });

    $connection->on('error', static function (Throwable $error) use ($uuid): void {
        printf("AudioSocket connection %s has error: %s\n", $uuid->toRfc4122(), $error);
    });

    $connection->on('dtmf', static function (DtmfSignal $signal) use ($uuid): void {
        printf("AudioSocket connection %s has received a \"%s\" DTMF signal\n", $uuid->toRfc4122(), $signal->value);
    });

    $connection->on('audio', static function (string $chunk, AudioFormat $audioFormat) use ($uuid): void {
        printf("AudioSocket connection %s has received a %d bytes chunk of %s audio.\n", $uuid->toRfc4122(), strlen($chunk), $audioFormat->getFormat());
    });

    $silenceLoop = Loop::addPeriodicTimer(AudioStreamInterface::CHUNK_DURATION, static function () use ($connection, $uuid): void {
        printf("Sending silence to AudioSocket connection %s.\n", $uuid->toRfc4122());

        $connection->sendAudio(AudioFormat::Slin, str_repeat("\0", AudioFormat::Slin->getChunkSize()));
    });

    Loop::addTimer(10, static function () use ($silenceLoop, $connection, $uuid): void {
        Loop::cancelTimer($silenceLoop);
        $connection->sendHangup();

        printf("Hangup AudioSocket connection %s.\n", $uuid->toRfc4122());
    });
});

printf("AudioSocket server wait for connections to %s\n", $server->getAddress() ?? 'unknown');

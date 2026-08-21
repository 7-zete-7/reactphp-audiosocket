<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use React\Socket\Connector;
use Symfony\Component\Uid\Uuid;
use Zete7\AudioSocket\Protocol\AudioFormat;
use Zete7\AudioSocket\Protocol\DtmfSignal;
use Zete7\React\AudioSocket\AudioStream\AudioStreamInterface;
use Zete7\React\AudioSocket\ClientConnection;

use function React\Async\await;

require_once dirname(__DIR__).'/vendor/autoload.php';

$uuid = Uuid::v7();

printf("AudioSocket UUID: %s\n", $uuid->toRfc4122());

$connector = new Connector([
    'tcp' => [
        'tcp_nodelay' => true,
    ],
]);

echo "Connecting to AudioSocket...\n";

$connection = await($connector->connect('tcp://127.0.0.1:9092'));
$client = new ClientConnection($connection, $uuid);

if ($client->isClosed()) {
    echo "AudioSocket is already closed.\n";
    exit(1);
}

echo "Connected to AudioSocket.\n";

$client->on('close', static function (): void {
    echo "AudioSocket was closed.\n";
});

$client->on('error', static function (Throwable $error): void {
    printf("AudioSocket has error: %s\n", $error);
});

$client->on('dtmf', static function (DtmfSignal $signal): void {
    printf("AudioSocket has received a \"%s\" DTMF signal\n", $signal->value);
});

$client->on('audio', static function (string $chunk, AudioFormat $audioFormat): void {
    printf("AudioSocket has received a %d bytes chunk of %s audio.\n", strlen($chunk), $audioFormat->getFormat());
});

$silenceLoop = Loop::addPeriodicTimer(AudioStreamInterface::CHUNK_DURATION, static function () use ($client): void {
    echo "Sending silence to AudioSocket.\n";

    $client->sendAudio(AudioFormat::Slin, str_repeat("\0", AudioFormat::Slin->getChunkSize()));
});

$client->on('close', static fn () => Loop::cancelTimer($silenceLoop));

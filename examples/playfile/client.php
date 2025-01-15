<?php

declare(strict_types=1);

use React\Socket\ConnectionInterface;
use React\Socket\TcpConnector;
use React\Stream\WritableResourceStream;
use Symfony\Component\Uid\Uuid;
use Zete7\React\AudioSocket\Client;
use Zete7\React\AudioSocket\Protocol\Message;
use Zete7\React\AudioSocket\ReceiveSlinStream;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$connector = new TcpConnector();

$connector->connect('tcp://127.0.0.1:8080')->then(static function (ConnectionInterface $connection): void {
    $id = Uuid::v7();
    $client = new Client($connection);

    if (!$client->send(Message::createIdMessage($id))) {
        printf("c   Failed to send ID message\n");
        $connection->close();

        return;
    }

    $reader = new ReceiveSlinStream($client);
    $writer = new WritableResourceStream(\fopen(__DIR__.'/client_result.slin', 'wb'));

    $reader->pipe($writer, ['end' => true]);

    $client->send(Message::createSilenceMessage());
});

<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

/**
 * Remote dump server. Receives dumps from debug-client.php
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);

$address = '127.0.0.1';
$port = 6666;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!socket_bind($sock, $address, $port)) {
    die('Could not bind to address.');
}
socket_listen($sock, 20);
socket_set_nonblock($sock);

$connections = [];
while (true) {
    $newConnection = socket_accept($sock);
    if ($newConnection) {
        if (is_resource($newConnection)) {
            $connections[] = $newConnection;
        }
    }

    foreach ($connections as $i => $connection) {
        $message = socket_read($connection, 1000000);
        if ($message === false) {
            if (socket_last_error() === 10035) {
                continue;
            }
            socket_close($connection);
            unset($connections[$i]);
        } elseif ($message) {
            echo $message;
        }
    }

    usleep(20);
}

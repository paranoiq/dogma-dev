<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

/**
 * Configure this file as auto-prepended to use shortcuts in any project.
 */

use Tracy\Debugger;
use Tracy\Dumper;

if (!class_exists(Debugger::class)) {
    if (file_exists(__DIR__ . '/../../../tracy/tracy/src/tracy.php')) {
        require_once __DIR__ . '/../../../tracy/tracy/src/tracy.php';
    } else {
        require_once __DIR__ . '/../vendor/tracy/tracy/src/tracy.php';
    }
}

if (!function_exists('d')) {
    /**
     * @param mixed ...$params
     */
    function d(...$params): void
    {
        Debugger::dump(...$params);
    }
}

if (!function_exists('bd')) {
    /**
     * @param mixed ...$params
     */
    function bd(...$params): void
    {
        Debugger::barDump(...$params);
    }
}

if (!function_exists('rd')) {
    /**
     * @param mixed $value
     * @param mixed|null $name
     * @param int|bool $depth
     * @param bool $showTrace
     */
    function rd($value, $name = null, $depth = 5, $showTrace = true): void
    {
        static $n;

        if ($depth === false) {
            $depth = 5;
            $showTrace = false;
        }

        if ($n === null) {
            $message = "\n" . date('Y-m-d H:i:s') . " ----------------------------------------------------------------------------------------------------------------\n";
            remoteDebugWrite($message);
            $n = 0;
        }

        if ($value instanceof \Dogma\Time\DateOrTime || $value instanceof \Dogma\Math\Interval\Interval) {
            // fill internal cache
            $value->format();
        }

        $options = [
            Dumper::DEPTH => $depth,
            Dumper::TRUNCATE => 1000,
            Dumper::LOCATION => false,
        ];
        $dump = Dumper::toTerminal($value, $options);
        $message = ($name ? $name . ': ' : '') . trim($dump) . "\n";
        if ($showTrace) {
            $trace = debug_backtrace();
            $message .= 'in ' . ($trace[0]['file'] ?? '?') . ':' . ($trace[0]['line'] ?? '?') . " ($n)\n";
        }

        remoteDebugWrite($message);
        $n++;
    }
}

if (!function_exists('debugWrite')) {
    function remoteDebugWrite(string $message): void
    {
        static $socket;

        if ($socket === null) {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!$socket) {
                die("Could not create socket to debug server.\n");
            }

            $result = socket_connect($socket, '127.0.0.1', 6666);
            if (!$result) {
                die("Could not connect to debug server.\n");
            }
        }

        $result = socket_write($socket, $message, strlen($message));
        if (!$result) {
            die("Could not send data to debug server.\n");
        }
    }
}

?>

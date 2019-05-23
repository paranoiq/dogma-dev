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

use Dogma\Pokeable;
use Tracy\Debugger;
use Tracy\Dumper;

if (!class_exists(Debugger::class)) {
    if (file_exists(__DIR__ . '/../../../tracy/tracy/src/tracy.php')) {
        require_once __DIR__ . '/../../../tracy/tracy/src/tracy.php';
    } elseif (file_exists(__DIR__ . '/../vendor/tracy/tracy/src/tracy.php')) {
        require_once __DIR__ . '/../vendor/tracy/tracy/src/tracy.php';
    } else {
        return;
    }
}

if (!isset(Dumper::$objectExporters[Pokeable::class])) {
    Dumper::$objectExporters[Pokeable::class] = function ($value) {
        $value->poke();

        return (array) $value;
    };
}

if (!function_exists('d')) {
    /**
     * @param mixed ...$params
     * @returm mixed
     */
    function d(...$params)
    {
        Debugger::dump(...$params);

        return $params[0];
    }
}

if (!function_exists('bd')) {
    /**
     * @param mixed ...$params
     * @return mixed
     */
    function bd(...$params)
    {
        Debugger::barDump(...$params);

        return $params[0];
    }
}

if (!function_exists('rd')) {
    /**
     * @param mixed $value
     * @param mixed|null $name
     * @param int|bool $depth
     * @param bool $showTrace
     * @return mixed
     */
    function rd($value, $name = null, $depth = 5, $showTrace = true)
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

        $options = [
            Dumper::DEPTH => $depth,
            Dumper::TRUNCATE => 5000,
            Dumper::LOCATION => false,
        ];
        $dump = Dumper::toTerminal($value, $options);
        $message = ($name ? $name . ': ' : '') . trim($dump) . "\n";
        if ($showTrace) {
            $trace = debug_backtrace();
            $message .= "\x1B[1;30min " . ($trace[0]['file'] ?? '?') . ':' . ($trace[0]['line'] ?? '?') . " ($n)\x1B[0m\n";
        }

        remoteDebugWrite($message);
        $n++;

        return $value;
    }
}

if (!function_exists('remoteDebugWrite')) {
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

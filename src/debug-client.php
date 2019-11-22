<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// spell-check-ignore: dt rl pid Pokeable

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

if (!class_exists('DogmaDebugTools')) {
    class DogmaDebugTools
    {

        /** @var resource */
        private static $socket;

        /** @var int */
        private static $n;

        /**
         * @param mixed[] $traces
         * @return string|null
         */
        public static function extractName(array $traces): ?string
        {
            $sourceTrace = $traces[0];
            $filePath = $sourceTrace['file'];
            if (!is_file($filePath) || !is_readable($filePath)) {
                return null;
            }
            $source = file_get_contents($filePath);
            $lines = explode("\n", $source);
            $lineIndex = $sourceTrace['line'] - 1;
            if (!isset($lines[$lineIndex])) {
                return null;
            }
            $line = $lines[$lineIndex];
            $result = preg_match('/rd\((.*?)[,)]/', $line, $match);
            if (!$result) {
                return null;
            }
            $expression = $match[1];
            if ($expression[0] === "'" || $expression[0] === '"' || ctype_digit($expression[0])) {
                return null;
            }
            if (substr($expression, -1) === '(') {
                $expression .= ')';
            }

            return $expression;
        }

        /**
         * @param mixed[] $trace
         * @return string|null
         */
        public static function formatTraceLine(array $trace): ?string
        {
            $filePath = $trace['file'] ?? null;
            if ($filePath === null) {
                return null;
            }
            $dirName = str_replace('\\', '/', dirname($filePath));
            $fileName = basename($filePath);

            $line = $trace['line'] ?? '?';
            $order = self::$n + 1;

            return "\x1B[1;30min $dirName/\x1B[0;37m$fileName\x1B[1;30m:\e[0;37m$line\e[1;30m ($order)\x1B[0m\n";
        }

        public static function remoteWrite(string $message): void
        {
            if (self::$socket === null) {
                self::remoteConnect();
            }

            if (self::$n === null) {
                $dt = new \DateTime();
                $time = $dt->format('Y-m-d H:i:s');
                $process = getmypid();
                $header = "\n$time (pid: $process) ";
                $message = $header . str_repeat('-', 120 - strlen($header)) . "\n" . $message;
            }

            $result = socket_write(self::$socket, $message, strlen($message));
            if (!$result) {
                die("Could not send data to debug server.\n");
            }

            self::$n++;
        }

        private static function remoteConnect(): void
        {
            self::$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!self::$socket) {
                die("Could not create socket to debug server.\n");
            }

            $result = socket_connect(self::$socket, '127.0.0.1', 6666);
            if (!$result) {
                die("Could not connect to debug server.\n");
            }
        }

    }
}

if (!function_exists('d')) {

    /**
     * @param mixed ...$params
     * @return mixed
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
     * @param int|bool $depth
     * @param int $showTraceLines
     * @return mixed
     */
    function rd($value, $depth = 5, $showTraceLines = 1)
    {
        if ($depth === false) {
            $depth = 5;
            $showTraceLines = 0;
        }

        $options = [
            Dumper::DEPTH => $depth,
            Dumper::TRUNCATE => 5000,
            Dumper::LOCATION => false,
        ];
        $dump = Dumper::toTerminal($value, $options);

        $traces = debug_backtrace();
        $name = DogmaDebugTools::extractName($traces);

        $message = ($name ? $name . ': ' : '') . trim($dump) . "\n";
        if ($showTraceLines > 0) {
            foreach ($traces as $i => $trace) {
                $message .= DogmaDebugTools::formatTraceLine($trace);
                if ($i + 1 >= $showTraceLines) {
                    break;
                }
            }
        }

        DogmaDebugTools::remoteWrite($message);

        return $value;
    }
}

if (!function_exists('rl')) {

    /**
     * @param mixed $label
     */
    function rl($label): void
    {
        $message = "\x1B[1;37m\x1B[41m $label \x1B[0m\n";

        DogmaDebugTools::remoteWrite($message);
    }
}

?>

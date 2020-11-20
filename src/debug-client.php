<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// spell-check-ignore: dt rl pid sapi URI rdm rda rf Pokeable Dumpable
// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

/**
 * Configure this file as auto-prepended to use shortcuts in any project.
 */

require_once __DIR__ . '/DogmaDebugColors.php';

use Dogma\Dumpable;
use Dogma\Pokeable;
use DogmaDebugColors as C;
use Tracy\Debugger;
use Tracy\Dumper;

if (!class_exists(Debugger::class)) {
    if (file_exists(__DIR__ . '/../../../tracy/tracy/src/tracy.php')) {
        // as an app dependency
        require_once __DIR__ . '/../../../tracy/tracy/src/tracy.php';
    } elseif (file_exists(__DIR__ . '/../vendor/tracy/tracy/src/tracy.php')) {
        // standalone (will fail if app includes tracy.php instead of auto-loading)
        require_once __DIR__ . '/../vendor/tracy/tracy/src/Tracy/Dumper/Dumper.php';
        require_once __DIR__ . '/../vendor/tracy/tracy/src/Tracy/Debugger/Debugger.php';
    } else {
        return;
    }
}

if (!isset(Dumper::$objectExporters[Dumpable::class])) {
    Dumper::$objectExporters[Dumpable::class] = static function ($value) {
        return [$value->dump()];
    };
}

if (!isset(Dumper::$objectExporters[Pokeable::class])) {
    Dumper::$objectExporters[Pokeable::class] = static function ($value) {
        $value->poke();

        return $value instanceof Dumpable ? [$value->dump()] : (array) $value;
    };
}

if (!class_exists('DogmaDebugTools')) {

    class DogmaDebugTools
    {

        /** @var Socket|resource */
        private static $socket;

        /** @var int */
        private static $counter;

        /** @var float[] */
        public static $timers = [];

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
            $order = self::$counter + 1;

            return C::gray("in $dirName/") . C::white($fileName) . C::gray(":") . C::white($line) . C::gray(" ($order)") . "\n";
        }

        public static function remoteWrite(string $message): void
        {
            if (self::$socket === null) {
                self::remoteConnect();
            }

            if (self::$counter === null) {
                $header = self::requestHeader();
                $message = $header . "\n" . $message;
            }

            $result = socket_write(self::$socket, $message, strlen($message));
            if (!$result) {
                die("Could not send data to debug server.\n");
            }

            self::$counter++;
        }

        private static function requestHeader(): string
        {
            $dt = new DateTime();
            $time = $dt->format('Y-m-d H:i:s');
            $sapi = PHP_SAPI;
            $header = "\n" . C::color(" $time $sapi ", C::WHITE, C::BLUE) . " ";

            if ($sapi === 'cli') {
                $process = getmypid();
                $header .= "(pid: $process) ";
            } else {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    $header .= 'AJAX ';
                }
                if (isset($_SERVER['REQUEST_METHOD'])) {
                    $header .= $_SERVER['REQUEST_METHOD'] . ' ';
                }
                if (!empty($_SERVER['REQUEST_URI'])) {
                    $header .= self::highlightUrl($_SERVER['REQUEST_URI']) . ' ';
                }
            }

            return C::padString($header, 120, '-');
        }

        private static function highlightUrl(string $url): string
        {
            $url = preg_replace('/([a-zA-Z0-9_-]+)=/', C::yellow('$1') . '=', $url);
            $url = preg_replace('/=([a-zA-Z0-9_-]+)/', '=' . C::lcyan('$1'), $url);
            $url = preg_replace('/[\\/?&=]/', C::gray('$0'), $url);

            return $url;
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

            register_shutdown_function(static function (): void {
                static $done = false;
                if (!$done) {
                    $start = self::$timers['total'];
                    $time = number_format((microtime(true) - $start) * 1000, 3, '.', ' ');
                    $memory = number_format(memory_get_peak_usage(true) / 1000000, 3, '.', ' ');
                    $message = "$time ms, $memory MB";
                    self::remoteWrite(C::color(" $message ", C::WHITE, C::BLUE) . "\n");

                    $done = true;
                }
            });
        }

    }

    DogmaDebugTools::$timers['total'] = microtime(true);
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
     * Remote dump
     *
     * @param mixed $value
     * @param int|bool $depth
     * @param int $showTraceLines
     * @return mixed
     */
    function rd($value, $depth = 5, int $showTraceLines = 1)
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

if (!function_exists('rdm')) {

    /**
     * Remotely dump multiple values under one name
     *
     * @param string|int $name
     * @param mixed ...$values
     */
    function rdm($name, ...$values): void
    {
        $options = [
            Dumper::DEPTH => 1,
            Dumper::TRUNCATE => 5000,
            Dumper::LOCATION => false,
        ];
        $dumps = [];
        foreach ($values as $value) {
            $dumps[] = trim(Dumper::toTerminal($value, $options));
        }
        $message = ($name ? $name . ': ' : '') . implode(C::gray(' | '), $dumps) . "\n";
        $trace = debug_backtrace()[1];
        $message .= DogmaDebugTools::formatTraceLine($trace);

        DogmaDebugTools::remoteWrite($message);
    }

}

if (!function_exists('rda')) {

    /**
     * Remotely dump associative array of names and values
     *
     * @param mixed[] $values
     */
    function rda(array $values): void
    {
        $options = [
            Dumper::DEPTH => 1,
            Dumper::TRUNCATE => 5000,
            Dumper::LOCATION => false,
        ];
        $dumps = [];
        foreach ($values as $key => $value) {
            $dumps[] = $key . C::gray(': ') . trim(Dumper::toTerminal($value, $options));
        }
        $message = implode(C::gray(' | '), $dumps) . "\n";
        $trace = debug_backtrace()[1];
        $message .= DogmaDebugTools::formatTraceLine($trace);

        DogmaDebugTools::remoteWrite($message);
    }

}

if (!function_exists('rf')) {

    /**
     * Remotely dump current function/method name
     */
    function rf(): void
    {
        $trace = debug_backtrace()[1];
        $class = $trace['class'] ?? null;
        $function = $trace['function'] ?? null;

        if ($class !== null) {
            $class = explode('\\', $class);
            $class = end($class);

            $message = C::color(" $class::$function() ", C::WHITE, C::RED);
        } else {
            $message = C::color(" $function() ", C::WHITE, C::RED);
        }

        DogmaDebugTools::remoteWrite($message . "\n");
    }

}

if (!function_exists('rl')) {

    /**
     * Remote dumper label
     *
     * @param mixed $label
     */
    function rl($label): void
    {
        $message = C::color(" $label ", C::WHITE, C::RED) . "\n";

        DogmaDebugTools::remoteWrite($message);
    }

}

if (!function_exists('t')) {

    /**
     * Remote dumper timer
     *
     * @param string|int|null $label
     */
    function t($label = ''): void
    {
        $label = (string) $label;

        if (isset(DogmaDebugTools::$timers[$label])) {
            $start = DogmaDebugTools::$timers[$label];
            DogmaDebugTools::$timers[$label] = microtime(true);
        } elseif (isset(DogmaDebugTools::$timers[null])) {
            $start = DogmaDebugTools::$timers[null];
            DogmaDebugTools::$timers[null] = microtime(true);
        } else {
            DogmaDebugTools::$timers[null] = microtime(true);
            return;
        }

        $time = number_format((microtime(true) - $start) * 1000, 3, '.', ' ');
        $label = $label ? ucfirst($label) : 'Timer';
        $message = C::color(" $label: $time ms ", C::WHITE, C::GREEN) . "\n";

        DogmaDebugTools::remoteWrite($message);
    }

}

?>

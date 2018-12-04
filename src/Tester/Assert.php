<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

namespace Dogma\Tester;

use Dogma\Equalable;
use Tester\Assert as NetteAssert;
use const SORT_STRING;
use function abs;
use function array_keys;
use function current;
use function get_class;
use function is_array;
use function is_finite;
use function is_float;
use function is_object;
use function ksort;
use function max;
use function next;

/**
 * Tester\Assert with fixed order of parameters
 * Added support for comparing object with Equalable interface
 */
class Assert
{

    private const EPSILON = 1e-10;

    /**
     * @param mixed $actual
     * @param mixed $expected
     * @param string|mixed|null $description
     */
    public static function same($actual, $expected, ?string $description = null): void
    {
        NetteAssert::same($expected, $actual, $description);
    }

    /**
     * @param mixed $actual
     * @param mixed $expected
     * @param string|mixed|null $description
     */
    public static function notSame($actual, $expected, ?string $description = null): void
    {
        NetteAssert::notSame($expected, $actual, $description);
    }

    /**
     * Added support for comparing object with Equalable interface
     * @param mixed $actual
     * @param mixed $expected
     * @param string|mixed|null $description
     */
    public static function equal($actual, $expected, ?string $description = null): void
    {
        if ($actual instanceof Equalable && $expected instanceof Equalable && get_class($actual) === get_class($expected)) {
            NetteAssert::$counter++;
            if (!$actual->equals($expected)) {
                self::fail(self::describe('%1 should be equal to %2', $description), $expected, $actual);
            }
        } else {
            NetteAssert::$counter++;
            if (!self::isEqual($expected, $actual)) {
                self::fail(self::describe('%1 should be equal to %2', $description), $expected, $actual);
            }
        }
    }

    /**
     * Added support for comparing object with Equalable interface
     * @param mixed $actual
     * @param mixed $expected
     * @param string|mixed|null $description
     */
    public static function notEqual($actual, $expected, ?string $description = null): void
    {
        if ($actual instanceof Equalable && $expected instanceof Equalable && get_class($actual) === get_class($expected)) {
            NetteAssert::$counter++;
            if ($actual->equals($expected)) {
                self::fail(self::describe('%1 should not be equal to %2', $description), $expected, $actual);
            }
        } else {
            NetteAssert::$counter++;
            if (self::isEqual($expected, $actual)) {
                self::fail(self::describe('%1 should not be equal to %2', $description), $expected, $actual);
            }
        }
    }

    /**
     * @param mixed $haystack
     * @param mixed $needle
     * @param string|mixed|null $description
     */
    public static function contains($haystack, $needle, ?string $description = null): void
    {
        NetteAssert::contains($needle, $haystack, $description);
    }

    /**
     * @param mixed $haystack
     * @param mixed $needle
     * @param string|mixed|null $description
     */
    public static function notContains($haystack, $needle, ?string $description = null): void
    {
        NetteAssert::notContains($needle, $haystack, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function true($actual, string $description = null): void
    {
        NetteAssert::true($actual, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function false($actual, string $description = null): void
    {
        NetteAssert::false($actual, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function null($actual, string $description = null): void
    {
        NetteAssert::null($actual, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function nan($actual, string $description = null): void
    {
        NetteAssert::nan($actual, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function truthy($actual, string $description = null): void
    {
        NetteAssert::truthy($actual, $description);
    }

    /**
     * @param mixed $actual
     */
    public static function falsey($actual, string $description = null): void
    {
        NetteAssert::falsey($actual, $description);
    }

    /**
     * @param mixed $actualValue
     * @param int|mixed $expectedCount
     * @param string|mixed|null $description
     */
    public static function count($actualValue, int $expectedCount, ?string $description = null): void
    {
        NetteAssert::count($expectedCount, $actualValue, $description);
    }

    /**
     * @param mixed $actualValue
     * @param string|mixed $expectedType
     * @param string|mixed|null $description
     */
    public static function type($actualValue, $expectedType, ?string $description = null): void
    {
        NetteAssert::type($expectedType, $actualValue, $description);
    }

    public static function exception(callable $function, string $class, string $message = null, $code = null): ?\Throwable
    {
        return NetteAssert::exception($function, $class, $message, $code);
    }

    public static function throws(callable $function, string $class, string $message = null, $code = null): ?\Throwable
    {
        return NetteAssert::exception($function, $class, $message, $code);
    }

    /**
     * @param callable $function
     * @param int|string|array $expectedType
     * @param string $expectedMessage message
     * @return \Throwable|null
     */
    public static function error(callable $function, $expectedType, string $expectedMessage = null): ?\Throwable
    {
        return NetteAssert::error($function, $expectedType, $expectedMessage);
    }

    public static function noError(callable $function): void
    {
        NetteAssert::error($function, []);
    }

    /**
     * @param mixed $actualValue
     * @param string|mixed $mask
     * @param string|mixed|null $description
     */
    public static function match($actualValue, $mask, ?string $description = null): void
    {
        NetteAssert::match($mask, $actualValue, $description);
    }

    /**
     * @param mixed $actualValue
     * @param mixed $file
     * @param string|mixed|null $description
     */
    public static function matchFile($actualValue, $file, ?string $description = null): void
    {
        NetteAssert::matchFile($file, $actualValue, $description);
    }

    /**
     * @param string|mixed $message
     * @param mixed|null $actual
     * @param mixed|null $expected
     */
    public static function fail($message, $actual = null, $expected = null): void
    {
        NetteAssert::fail($message, $expected, $actual);
    }

    /**
     * Added support for comparing object with Equalable interface
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $level
     * @param mixed|null $objects
     * @return bool
     * @internal
     */
    public static function isEqual($expected, $actual, $level = 0, $objects = null): bool
    {
        if ($level > 10) {
            throw new \Exception('Nesting level too deep or recursive dependency.');
        }

        if (is_float($expected) && is_float($actual) && is_finite($expected) && is_finite($actual)) {
            $diff = abs($expected - $actual);
            return ($diff < self::EPSILON) || ($diff / max(abs($expected), abs($actual)) < self::EPSILON);
        }

        if (is_object($expected) && is_object($actual) && get_class($expected) === get_class($actual)) {
            /* start */
            if ($expected instanceof Equalable && $actual instanceof Equalable) {
                return $expected->equals($actual);
            }
            /* end */
            $objects = $objects ? clone $objects : new \SplObjectStorage();
            if (isset($objects[$expected])) {
                return $objects[$expected] === $actual;
            } elseif ($expected === $actual) {
                return true;
            }
            $objects[$expected] = $actual;
            $objects[$actual] = $expected;
            $expected = (array) $expected;
            $actual = (array) $actual;
        }

        if (is_array($expected) && is_array($actual)) {
            ksort($expected, SORT_STRING);
            ksort($actual, SORT_STRING);
            if (array_keys($expected) !== array_keys($actual)) {
                return false;
            }

            foreach ($expected as $value) {
                if (!self::isEqual($value, current($actual), $level + 1, $objects)) {
                    return false;
                }
                next($actual);
            }
            return true;
        }

        return $expected === $actual;
    }

    /**
     * @param mixed $reason
     * @param mixed $description
     * @return string
     */
    private static function describe($reason, $description): string
    {
        return ($description ? $description . ': ' : '') . $reason;
    }

    /**
     * @param mixed $obj
     * @param \Closure $closure
     */
    public static function with($obj, \Closure $closure)
    {
        NetteAssert::with($obj, $closure);
    }

}

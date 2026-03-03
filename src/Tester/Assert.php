<?php declare(strict_types = 1);
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// spell-check-ignore: nan truthy falsey

namespace Dogma\Tester;

use Closure;
use Countable;
use Dogma\Equalable;
use Exception;
use SplObjectStorage;
use Tester\Assert as NetteAssert;
use Tester\Expect;
use Throwable;
use function abs;
use function array_keys;
use function current;
use function is_array;
use function is_finite;
use function is_float;
use function is_object;
use function ksort;
use function max;
use function next;
use const SORT_STRING;

/**
 * Tester\Assert with consistent order of parameters ($actual is always first)
 * Added support for comparing object with Equalable interface
 */
class Assert
{

    private const EPSILON = 1e-10;

    public static function same(mixed $actual, mixed $expected, ?string $description = null): void
    {
        NetteAssert::same($expected, $actual, $description);
    }

    public static function notSame(mixed $actual, mixed $expected, ?string $description = null): void
    {
        NetteAssert::notSame($expected, $actual, $description);
    }

    /**
     * Added support for comparing object with Equalable interface
     */
    public static function equal(mixed $actual, mixed $expected, ?string $description = null): void
    {
        if ($actual instanceof Equalable && $expected instanceof Equalable && $actual::class === $expected::class) {
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
     */
    public static function notEqual(mixed $actual, mixed $expected, ?string $description = null): void
    {
        if ($actual instanceof Equalable && $expected instanceof Equalable && $actual::class === $expected::class) {
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
     * @param array<mixed>|string $haystack
     */
    public static function contains(array|string $haystack, mixed $needle, ?string $description = null): void
    {
        NetteAssert::contains($needle, $haystack, $description);
    }

    /**
     * @param array<mixed>|string $haystack
     */
    public static function notContains(array|string $haystack, mixed $needle, ?string $description = null): void
    {
        NetteAssert::notContains($needle, $haystack, $description);
    }

    public static function true(mixed $actual, ?string $description = null): void
    {
        NetteAssert::true($actual, $description);
    }

    public static function false(mixed $actual, ?string $description = null): void
    {
        NetteAssert::false($actual, $description);
    }

    public static function null(mixed $actual, ?string $description = null): void
    {
        NetteAssert::null($actual, $description);
    }

    public static function nan(mixed $actual, ?string $description = null): void
    {
        NetteAssert::nan($actual, $description);
    }

    public static function truthy(mixed $actual, ?string $description = null): void
    {
        NetteAssert::truthy($actual, $description);
    }

    public static function falsey(mixed $actual, ?string $description = null): void
    {
        NetteAssert::falsey($actual, $description);
    }

    /**
     * @param array<mixed>|Countable $actualValue
     */
    public static function count(array|Countable $actualValue, int $expectedCount, ?string $description = null): void
    {
        NetteAssert::count($expectedCount, $actualValue, $description);
    }

    public static function type(mixed $actualValue, string|object $expectedType, ?string $description = null): void
    {
        NetteAssert::type($expectedType, $actualValue, $description);
    }

    /**
     * @param class-string<Throwable> $class
     */
    public static function exception(callable $function, string $class, ?string $message = null, int|string|null $code = null): ?Throwable
    {
        return NetteAssert::exception($function, $class, $message, $code);
    }

    /**
     * @param class-string<Throwable> $class
     */
    public static function throws(callable $function, string $class, ?string $message = null, int|string|null $code = null): ?Throwable
    {
        return NetteAssert::exception($function, $class, $message, $code);
    }

    /**
     * @param int|string|array<mixed> $expectedType
     */
    public static function error(callable $function, int|string|array $expectedType, ?string $expectedMessage = null): ?Throwable
    {
        return NetteAssert::error($function, $expectedType, $expectedMessage);
    }

    public static function noError(callable $function): void
    {
        NetteAssert::error($function, []);
    }

    public static function match(string $actualValue, string $pattern, ?string $description = null): void
    {
        NetteAssert::match($pattern, $actualValue, $description);
    }

    public static function matchFile(string $actualValue, string $file, ?string $description = null): void
    {
        NetteAssert::matchFile($file, $actualValue, $description);
    }

    public static function fail(string $message, mixed $actual = null, mixed $expected = null): void
    {
        NetteAssert::fail($message, $expected, $actual);
    }

    /**
     * Added support for comparing object with Equalable interface
     *
     * @param SplObjectStorage<object, mixed>|null $objects
     * @internal
     */
    public static function isEqual(mixed $expected, mixed $actual, int $level = 0, ?SplObjectStorage $objects = null): bool
    {
        switch (true) {
            case $level > 10:
                throw new Exception('Nesting level too deep or recursive dependency.');
            case $expected instanceof Expect:
                $expected($actual);

                return true;
            case is_float($expected) && is_float($actual) && is_finite($expected) && is_finite($actual):
                $diff = abs($expected - $actual);

                return ($diff < self::EPSILON) || ($diff / max(abs($expected), abs($actual)) < self::EPSILON);
            case is_object($expected) && is_object($actual) && $expected::class === $actual::class:
                /* start */
                if ($expected instanceof Equalable && $actual instanceof Equalable) {
                    return $expected->equals($actual);
                }
                /* end */
                $objects = $objects ? clone $objects : new SplObjectStorage(); // @phpstan-ignore-line only boolean...
                if (isset($objects[$expected])) {
                    return $objects[$expected] === $actual;
                } elseif ($expected === $actual) {
                    return true;
                }

                $objects[$expected] = $actual;
                $objects[$actual] = $expected;
                $expected = (array) $expected;
                $actual = (array) $actual;
                // break omitted

            case is_array($expected) && is_array($actual):
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
            default:
                return $expected === $actual;
        }
    }

    private static function describe(string $reason, ?string $description): string
    {
        return ($description ? $description . ': ' : '') . $reason; // @phpstan-ignore-line only boolean...
    }

    /**
     * @param class-string|object $obj
     */
    public static function with(object|string $obj, Closure $closure): void
    {
        NetteAssert::with($obj, $closure);
    }

}

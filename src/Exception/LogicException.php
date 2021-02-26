<?php declare(strict_types=1);

namespace Lmc\Steward\Exception;

class LogicException extends \LogicException implements StewardExceptionInterface
{
    public static function forDelayWithNotExistingTestcase(string $className, string $delay): self
    {
        $message = sprintf(
            'Testcase "%s" has @delayAfter dependency on "%s", but this testcase is not defined.',
            $className,
            $delay
        );

        return new self($message);
    }

    public static function forDelayWithMissingTestcase(string $className, string $delay): self
    {
        $message = sprintf(
            'Testcase "%s" has defined @delayMinutes %d minutes, but doesn\'t have defined the testcase to run after'
            . ' using @delayAfter',
            $className,
            $delay
        );

        return new self($message);
    }

    public static function forCyclicDependencyInGraph(): self
    {
        $message = 'Cannot build tree graph from tests dependencies. Probably some cyclic dependency is present.';

        return new self($message);
    }
}

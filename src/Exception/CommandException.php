<?php declare(strict_types=1);

namespace Lmc\Steward\Exception;

use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * Runtime exception from Steward Command (ie. wrong CLI arguments).
 * It also implements `Symfony\Component\Console\Exception\ExceptionInterface` to not break Symfony Command behavior.
 */
class CommandException extends \RuntimeException implements ExceptionInterface, StewardExceptionInterface
{
    public static function forUnsupportedBrowser(string $browserName, array $supportedBrowsers): self
    {
        $message = sprintf(
            'Browser "%s" is not supported (use one of: %s)',
            $browserName,
            implode(', ', $supportedBrowsers)
        );

        return new self($message);
    }

    public static function forNotAccessibleResultsFile(string $fileName, string $option): self
    {
        $message = sprintf(
            'Cannot read results file "%s", make sure it is accessible (or use --%s option if it is stored elsewhere)',
            $fileName,
            $option
        );

        return new self($message);
    }
}

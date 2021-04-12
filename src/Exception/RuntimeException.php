<?php declare(strict_types=1);

namespace Lmc\Steward\Exception;

class RuntimeException extends \RuntimeException implements StewardExceptionInterface
{
    private const XDEBUG_DOCS_URL = 'https://github.com/lmc-eu/steward/wiki/Debugging-Selenium-tests-with-Steward';

    public static function forReflectionErrorWhenLoadedFromFile(
        string $className,
        string $fileName,
        \ReflectionException $previous
    ): self {
        $message = sprintf(
            'Error loading class "%s" from file "%s". Make sure the class name and namespace matches the file path.',
            $className,
            $fileName
        );

        return new self($message, 0, $previous);
    }

    public static function forNoClassInFile(string $fileName): self
    {
        return new self(sprintf('No class found in file "%s"', $fileName));
    }

    public static function forMultipleClassesInOneFile(string $fileName, int $count): self
    {
        $message = sprintf(
            'File "%s" contains definition of %d classes. However, each class must be defined in its own'
            . ' separate file.',
            $fileName,
            $count
        );

        return new self($message);
    }

    public static function forDuplicateClassName(string $className): self
    {
        $message = sprintf(
            'Testcase with name "%s" was already added, make sure you don\'t have duplicate class names.',
            $className
        );

        return new self($message);
    }

    public static function forMissingXdebugConfiguration(string $configuration): self
    {
        $message = sprintf(
            'PHP configuration %s to enable remote debugging.' . "\n"
            . '💡 See %s for help and more information',
            $configuration,
            self::XDEBUG_DOCS_URL
        );

        return new self($message);
    }

    public static function forMissingXdebugExtension(): self
    {
        $message = sprintf(
            'Extension Xdebug is not loaded or installed.' . "\n"
            . '💡 See %s for help and more information',
            self::XDEBUG_DOCS_URL
        );

        return new self($message);
    }
}

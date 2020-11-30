<?php declare(strict_types=1);

namespace Lmc\Steward\Exception;

class RuntimeException extends \RuntimeException implements StewardExceptionInterface
{
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
}

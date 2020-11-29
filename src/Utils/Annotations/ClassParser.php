<?php declare(strict_types=1);

namespace Lmc\Steward\Utils\Annotations;

use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use Symfony\Component\Finder\SplFileInfo;

class ClassParser
{
    public static function readClassNameFromFile(SplFileInfo $file): string
    {
        $reflection = new ClassReflector(
            new SingleFileSourceLocator($file->getRealPath(), (new BetterReflection())->astLocator())
        );

        $classesInFile = $reflection->getAllClasses();
        self::assertOneClassInFile($classesInFile, $file);

        return $classesInFile[0]->getName();
    }

    private static function assertOneClassInFile(array $classesInFile, SplFileInfo $file): void
    {
        if (count($classesInFile) === 0) {
            throw new \RuntimeException(sprintf('No class found in file "%s"', $file->getRelativePathname()));
        }

        if (count($classesInFile) > 1) {
            throw new \RuntimeException(
                sprintf(
                    'File "%s" contains definition of %d classes. However, each class must be defined in its own'
                    . ' separate file.',
                    $file->getRelativePathname(),
                    count($classesInFile)
                )
            );
        }
    }
}

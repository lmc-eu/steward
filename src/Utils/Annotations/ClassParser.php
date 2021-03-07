<?php declare(strict_types=1);

namespace Lmc\Steward\Utils\Annotations;

use Lmc\Steward\Exception\RuntimeException;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Parse PHP files to get information of the class they contains.
 */
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
            throw RuntimeException::forNoClassInFile($file->getRelativePathname());
        }

        if (count($classesInFile) > 1) {
            throw RuntimeException::forMultipleClassesInOneFile($file->getRelativePathname(), count($classesInFile));
        }
    }
}

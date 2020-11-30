<?php declare(strict_types=1);

namespace Lmc\Utils\Annotations;

use Lmc\Steward\Utils\Annotations\ClassParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @covers \Lmc\Steward\Utils\Annotations\ClassParser
 */
class ClassParserTest extends TestCase
{
    public function testShouldGetClassNameFromFile(): void
    {
        $file = $this->createFileInfo('ClassNoDocBlock.php');

        $className = ClassParser::readClassNameFromFile($file);

        $this->assertSame('Lmc\Steward\Utils\Annotations\Fixtures\ClassNoDocBlock', $className);
    }

    public function testShouldThrowExceptionForMultipleClassesInOneFile(): void
    {
        $file = $this->createFileInfo('MultipleClassesInFile.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageRegExp('/^File ".+MultipleClassesInFile.php" contains definition of 2 classes\./');
        ClassParser::readClassNameFromFile($file);
    }

    public function testShouldThrowExceptionForNoClassInOneFile(): void
    {
        $file = $this->createFileInfo('NoClassInFile.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageRegExp('/^No class found in file ".+NoClassInFile.php"/');
        ClassParser::readClassNameFromFile($file);
    }

    private function createFileInfo(string $fileName): SplFileInfo
    {
        return new SplFileInfo(__DIR__ . '/Fixtures/' . $fileName, 'Fixtures/', 'Fixtures/' . $fileName);
    }
}

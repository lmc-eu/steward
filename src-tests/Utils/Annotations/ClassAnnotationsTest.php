<?php declare(strict_types=1);

namespace Lmc\Steward\Utils\Annotations;

use Lmc\Steward\Utils\Annotations\Fixtures\ClassEmptyDockBlock;
use Lmc\Steward\Utils\Annotations\Fixtures\ClassKeyAnnotations;
use Lmc\Steward\Utils\Annotations\Fixtures\ClassKeyValueAnnotations;
use Lmc\Steward\Utils\Annotations\Fixtures\ClassMixedAnnotations;
use Lmc\Steward\Utils\Annotations\Fixtures\ClassNoDocBlock;
use Lmc\Steward\Utils\Annotations\Fixtures\ClassWithMethods;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Lmc\Steward\Utils\Annotations\ClassAnnotations
 */
class ClassAnnotationsTest extends TestCase
{
    /**
     * @dataProvider provideClass
     */
    public function testShouldGetAnnotationsForClass(string $className, array $expectedAnnotations): void
    {
        $annotations = ClassAnnotations::getAnnotationsForClass($className);

        $this->assertSame($expectedAnnotations, $annotations);
    }

    /**
     * @return array[]
     */
    public function provideClass(): array
    {
        return [
            'Class with empty doc block' => [
                ClassEmptyDockBlock::class,
                [],
            ],
            'Class having no doc block at all' => [
                ClassNoDocBlock::class,
                [],
            ],
            'Class with key-only annotations, without values' => [
                ClassKeyAnnotations::class,
                [
                    'first' => ['', ''],
                    'second' => [''],
                    'third' => [''],
                ],
            ],
            'Class with key-value annotations' => [
                ClassKeyValueAnnotations::class,
                [
                    'first' => ['First value', 'Second value of first'],
                    'second' => ['Second value'],
                    'third' => ['Third "special" value'],
                    'see' => [''],
                ],
            ],
            'Class with mixed key-only and key-value annotations' => [
                ClassMixedAnnotations::class,
                [
                    'first' => ['', 'First with some value'],
                    'second' => [''],
                    'third' => [''],
                    'fourth' => ['Fourth value'],
                ],
            ],
        ];
    }

    public function testShouldGetAnnotationsForInstances(): void
    {
        $annotations = ClassAnnotations::getAnnotationsForInstance(new ClassKeyAnnotations());

        $this->assertSame(
            [
                'first' => ['', ''],
                'second' => [''],
                'third' => [''],
            ],
            $annotations
        );
    }

    /**
     * @dataProvider provideMethod
     */
    public function testShouldGetAnnotationsForMethodInClass(string $methodName, array $expectedAnnotations): void
    {
        $classInstance = new ClassWithMethods();

        $annotations = ClassAnnotations::getAnnotationsForMethodOfInstance($classInstance, $methodName);

        $this->assertSame($expectedAnnotations, $annotations);
    }

    /**
     * @return array[]
     */
    public function provideMethod(): array
    {
        return [
            'Method with empty doc block' => [
                'methodWithEmptyAnnotations',
                [],
            ],
            'Method having no doc block at all' => [
                'methodWithout',
                [],
            ],
            'Method with key-only annotations, without values' => [
                'methodWithKeys',
                [
                    'first' => ['', ''],
                    'second' => [''],
                    'third' => [''],
                ],
            ],
            'Method with key-value annotations' => [
                'methodWithKeyValues',
                [
                    'first' => ['First value', 'Second value of first'],
                    'second' => ['Second value'],
                    'third' => ['Third "special" @value!'],
                ],
            ],
            'Method with mixed key-only and key-value annotations' => [
                'methodWithMixedKeyValues',
                [
                    'first' => ['', 'First with some value'],
                    'second' => ['Second with value', ''],
                    'third' => [''],
                ],
            ],
        ];
    }
}

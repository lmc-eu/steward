<?php declare(strict_types=1);

namespace Lmc\Steward\Utils\Annotations\Fixtures;

/**
 * This class has few methods with various annotations.
 *
 */
class ClassWithMethods
{
    public function methodWithout(): void
    {
        // method without annotations
    }

    /**
     *
     */
    public function methodWithEmptyAnnotations(): void
    {
        // method with empty docBlock
    }

    /**
     * Some text not treated as @annotation
     *
     * @first
     * @second
     * @first
     * @third
     */
    public function methodWithKeys(): void
    {
    }

    /**
     * @first First value
     * @second Second value
     * @first Second value of first
     * @third Third "special" @value!
     */
    public function methodWithKeyValues(): void
    {
    }

    /**
     * @first
     * @second Second with value
     * @first First with some value
     * @second
     * @third
     */
    public function methodWithMixedKeyValues(): void
    {
    }
}

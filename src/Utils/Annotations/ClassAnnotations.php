<?php declare(strict_types=1);

namespace Lmc\Steward\Utils\Annotations;

use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * Read simple class annotations from their doc-blocks.
 */
class ClassAnnotations
{
    public static function getAnnotationsForInstance($object): array
    {
        $reflectionObject = new \ReflectionObject($object);

        return static::getAnnotations($reflectionObject);
    }

    /**
     * @throws \ReflectionException
     */
    public static function getAnnotationsForClass(string $className): array
    {
        $reflectionClass = new \ReflectionClass($className);

        return static::getAnnotations($reflectionClass);
    }

    public static function getAnnotationsForMethodOfInstance($object, string $method): array
    {
        $reflectionMethod = new \ReflectionMethod($object, $method);

        return static::getAnnotations($reflectionMethod);
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod $reflectionClass
     */
    private static function getAnnotations($reflectionClass): array
    {
        if (empty($reflectionClass->getDocComment())) {
            return [];
        }

        $docBlockFactory = DocBlockFactory::createInstance();
        $docBlock = $docBlockFactory->create($reflectionClass);

        $annotations = [];

        /** @var BaseTag $tag */
        foreach ($docBlock->getTags() as $tag) {
            $annotationToAdd = '';
            if ($tag->getDescription() !== null) {
                $annotationToAdd = $tag->getDescription()->getBodyTemplate();
            }

            $annotations[$tag->getName()][] = $annotationToAdd;
        }

        return $annotations;
    }
}

<?php declare(strict_types=1);

namespace Lmc\Steward;

use Lmc\Steward\Test\AbstractTestCase;
use PHPUnit\Framework\MockObject\Matcher\AnyInvokedCount;
use PHPUnit\Framework\MockObject\MockObject;

trait MockAbstractTestCaseWithNameTrait
{
    abstract protected function getMockForAbstractClass(
        $originalClassName,
        array $arguments = [],
        $mockClassName = '',
        $callOriginalConstructor = true,
        $callOriginalClone = true,
        $callAutoload = true,
        $mockedMethods = [],
        $cloneArguments = false
    ): MockObject;

    abstract public static function any(): AnyInvokedCount;

    /**
     * @return AbstractTestCase|MockObject
     */
    public function getAbstractTestCaseMock(
        string $testCaseName,
        string $testMethodName
    ): MockObject {
        /** @var AbstractTestCase|MockObject $testCase */
        $testCase = $this->getMockForAbstractClass(
            AbstractTestCase::class,
            [],
            $testCaseName,
            false,
            true,
            true,
            ['getName']
        );

        $testCase->expects($this->any())
            ->method('getName')
            ->willReturn($testMethodName);

        return $testCase;
    }
}

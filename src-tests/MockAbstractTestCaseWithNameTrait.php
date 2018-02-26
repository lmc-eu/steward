<?php declare(strict_types=1);

namespace Lmc\Steward;

use Lmc\Steward\Test\AbstractTestCase;

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
    );

    /**
     * @return \PHPUnit_Framework_MockObject_Matcher_AnyInvokedCount
     */
    abstract public static function any();

    /**
     * @return AbstractTestCase|\PHPUnit_Framework_MockObject_MockObject
     */
    public function getAbstractTestCaseMock(
        string $testCaseName,
        string $testMethodName
    ): \PHPUnit_Framework_MockObject_MockObject {
        /** @var AbstractTestCase|\PHPUnit_Framework_MockObject_MockObject $testCase */
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

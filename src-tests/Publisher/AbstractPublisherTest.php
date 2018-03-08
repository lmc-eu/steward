<?php declare(strict_types=1);

namespace Lmc\Steward\Publisher;

use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\BaseTestRunner;

/**
 * @covers \Lmc\Steward\Publisher\AbstractPublisher
 */
class AbstractPublisherTest extends TestCase
{
    /**
     * @test
     */
    public function shouldProvideStewardResultForPhpUnitTestStatus(): void
    {
        $result = AbstractPublisher::getResultForPhpUnitTestStatus(BaseTestRunner::STATUS_PASSED);

        $this->assertSame(AbstractPublisher::TEST_RESULT_PASSED, $result);
    }

    /**
     * @test
     */
    public function shouldThrowExceptionForUnknownPhpuntTestStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PHPUnit test status "1337" is not known to Steward');
        AbstractPublisher::getResultForPhpUnitTestStatus(1337);
    }
}

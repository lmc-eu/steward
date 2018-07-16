<?php declare(strict_types=1);

namespace Lmc\Steward\Process;

use Lmc\Steward\Console\Style\StewardStyle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \Lmc\Steward\Process\ExecutionLoop
 */
class ExecutionLoopTest extends TestCase
{
    /** @test */
    public function shouldExecuteEmptyProcessSet(): void
    {
        $emptyProcessSet = new ProcessSet();
        $output = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);

        $loop = new ExecutionLoop(
            $emptyProcessSet,
            new StewardStyle(new StringInput(''), $output),
            new MaxTotalDelayStrategy()
        );

        $result = $loop->start();

        $this->assertTrue($result);
        $this->assertContains('[OK] Testcases executed: 0', $output->fetch());
    }
}

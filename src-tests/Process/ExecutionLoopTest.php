<?php declare(strict_types=1);

namespace Lmc\Steward\Process;

use Lmc\Steward\Console\Style\StewardStyle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @covers \Lmc\Steward\Process\ExecutionLoop
 */
class ExecutionLoopTest extends TestCase
{
    /** @test */
    public function shouldExecuteEmptyProcessSet(): void
    {
        $emptyProcessSet = new ProcessSet();
        $outputBuffer = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);

        $loop = new ExecutionLoop(
            $emptyProcessSet,
            new StewardStyle(new StringInput(''), $outputBuffer),
            new MaxTotalDelayStrategy()
        );

        $result = $loop->start();
        $output = $outputBuffer->fetch();

        $this->assertTrue($result, 'Exception loop did not finish successfully, output was: ' . "\n" . $output);
        $this->assertContains('[OK] Testcases executed: 0', $output);
    }

    /** @test */
    public function shouldDequeueProcessesWithoutDelayOnStartup(): void
    {
        $noDelayTest = new ProcessWrapper(new Process('echo NoDelay'), 'NoDelay');
        $delayedTest = new ProcessWrapper(new Process('echo Delayed'), 'Delayed');
        $delayedTest->setDelay('NoDelay', 0.001);

        $processSet = new ProcessSet();
        $processSet->add($noDelayTest);
        $processSet->add($delayedTest);

        // Preconditions - both processes should be queued after being added
        $processes = $processSet->get(ProcessWrapper::PROCESS_STATUS_QUEUED);
        $this->assertCount(2, $processes);

        $outputBuffer = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);
        $loop = new ExecutionLoop(
            $processSet,
            new StewardStyle(new StringInput(''), $outputBuffer),
            new MaxTotalDelayStrategy()
        );

        $result = $loop->start();
        $output = $outputBuffer->fetch();

        $this->assertTrue($result, 'Exception loop did not finish successfully, output was: ' . "\n" . $output);

        $this->assertContains('Testcase "NoDelay" is prepared to be run', $output);
        $this->assertContains(
            'Testcase "Delayed" is queued to be run 0.0 minutes after testcase "NoDelay" is finished',
            $output
        );

        $this->assertContains('Dequeing testcase "Delayed"', $output);
    }
}

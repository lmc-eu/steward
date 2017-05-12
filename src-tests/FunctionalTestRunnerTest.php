<?php

namespace Lmc\Steward;

use Lmc\Steward\Console\Application;
use Lmc\Steward\Console\Command\RunCommand;
use Lmc\Steward\Console\EventListener\ListenerInstantiator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Run Steward-based tests (from FunctionalTests/ directory) which tests Steward components through real Steward run.
 * Quis custodiet ipsos custodes?
 *
 * @group integration
 * @group functional
 * @runTestsInSeparateProcesses
 */
class FunctionalTestRunnerTest extends TestCase
{
    /** @var RunCommand */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    protected function setUp()
    {
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->setDispatcher($dispatcher);

        // Search for test listeners and attach them to dispatcher
        $instantiator = new ListenerInstantiator();
        $instantiator->setSearchPathPattern('Fixtures/');
        $instantiator->instantiate($dispatcher, __DIR__ . '/FunctionalTests');

        $application->add(new RunCommand($dispatcher));

        $this->command = $application->find('run');
        $this->tester = new CommandTester($this->command);
    }

    public function testShouldExecuteSimpleTests()
    {
        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                'environment' => 'staging',
                'browser' => 'firefox',
                '--tests-dir' => __DIR__ . '/FunctionalTests',
                '--logs-dir' => __DIR__ . '/FunctionalTests/logs',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_DEBUG]
        );

        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode(), $output);
    }
}

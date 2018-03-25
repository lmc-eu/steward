<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Command;

use Lmc\Steward\Console\Application;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Lmc\Steward\Console\Command\GenerateTimelineCommand
 */
class GenerateTimelineCommandTest extends TestCase
{
    /** @var GenerateTimelineCommand */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    protected function setUp(): void
    {
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->add(new GenerateTimelineCommand($dispatcher));

        /** @var GenerateTimelineCommand $command */
        $command = $application->find('generate-timeline');
        $this->command = $command;
        $this->tester = new CommandTester($this->command);
    }

    public function testShouldShowErrorIfResultsFileCannotBeFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot read results file "/not/accessible.xml"');

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--results-file' => '/not/accessible.xml',
            ]
        );
    }

    public function testShouldOutputHtmlFileWithJsonData(): void
    {
        /** @var MockObject|Filesystem $filesystemMock */
        $filesystemMock = $this->createMock(Filesystem::class);
        $filesystemMock->expects($this->once())
            ->method('dumpFile')
            ->with(
                $this->equalTo('/foo/bar.html'),
                $this->logicalAnd(
                    $this->matches('%Avar groups = new vis.DataSet(%w[{"id":%s,"content":"%s","title":"%s"}%s]%w);%A'),
                    $this->matches(
                        '%Avar items = new vis.DataSet(%w'
                        . '[{"group":%s,"content":"%s","title":"%s","start":"%s","end":"%S","className":"%S"}%s]%w);%A'
                    )
                )
            );

        $this->command->setFilesystem($filesystemMock);

        $this->tester->execute(
            [
                'command' => $this->command->getName(),
                '--results-file' => __DIR__ . '/../../Timeline/Fixtures/results.xml',
                '--output-file' => '/foo/bar.html',
            ]
        );

        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertContains('[OK] Timeline generated to file "/foo/bar.html"', $output);
    }
}

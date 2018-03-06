<?php declare(strict_types=1);

namespace Lmc\Steward\Console;

use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    public function testShouldAddCustomInputDefinition(): void
    {
        $application = new Application();

        $inputDefinition = $application->getDefinition();

        // Custom definition should be added
        $this->assertTrue($inputDefinition->hasOption(Application::OPTION_CONFIGURATION));
        $this->assertTrue($inputDefinition->hasShortcut('c'));

        // Original definition should still be present
        $this->assertTrue($inputDefinition->hasOption('help'));
        $this->assertTrue($inputDefinition->hasOption('quiet'));
        $this->assertTrue($inputDefinition->hasOption('verbose'));
    }
}

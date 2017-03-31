<?php

namespace Lmc\Steward\Console;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function testShouldAddCustomInputDefinition()
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

<?php

namespace Lmc\Steward\Test;

use Nette\Utils\Strings;

/**
 * Listener to take screenshot on each error or failure.
 */
class ScreenshotListener extends \PHPUnit_Framework_BaseTestListener
{
    public function addError(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
        $this->takeScreenshot($test, $e, $time);
    }

    public function addFailure(\PHPUnit_Framework_Test $test, \PHPUnit_Framework_AssertionFailedError $e, $time)
    {
        $this->takeScreenshot($test, $e, $time);
    }

    /**
     * Take screenshot and save it.
     * @param AbstractTestCase $test
     * @param Exception $e
     * @param $time
     */
    private function takeScreenshot(AbstractTestCase $test, \Exception $e, $time)
    {
        $savePath = __DIR__ . '/../../logs/';

        $testIdentifier = Strings::webalize(get_class($test), null, $lower = false)
            . '-'
            . Strings::webalize($test->getName(), null, $lower = false)
            . '-'
            . date('Y-m-d-H-i-s');

        $test->log('Taking screenshot because: "%s"', $e->getMessage());

        if (!$test->wd instanceof \RemoteWebDriver) {
            $test->warn('WebDriver instance not found, cannot take screenshot.');
            return;
        }

        $test->wd->takeScreenshot($savePath . $testIdentifier . '.png');
        $test->log('Screenshot saved to file "%s" ', $e->getMessage(), $testIdentifier . '.png');
    }
}

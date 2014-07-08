<?php

namespace Lmc\Steward\Test;

use Nette\Utils\Strings;

/**
 * Listener to take screenshot on each error or failure
 */
class ErrorScreenshotListener extends \PHPUnit_Framework_BaseTestListener
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
     * Take screenshot and save it
     * @param PHPUnit_Framework_Test|LmcTestCase $test
     * @param Exception                          $e
     * @param $time
     */
    private function takeScreenshot(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
        if (!$test instanceof AbstractTestCase) {
            throw new \InvalidArgumentException('Test case must be descendant of Lmc\Steward\Test\AbstractTestCase');
        }
        $test->log('Taking screenshot because: %s', $e->getMessage());
        if (!$test->wd instanceof \RemoteWebDriver) {
            $test->log('No webdriver, no screenshot.');
        }

        $test->wd->takeScreenshot(
            __DIR__ . '/../../logs/'
            . Strings::webalize(get_class($test))
            . '-'
            . Strings::webalize($test->getName())
            . '-'
            . date('Y-m-d-H-i-s')
            . '.png'
        );
    }
}

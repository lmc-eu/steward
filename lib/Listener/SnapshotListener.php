<?php

namespace Lmc\Steward\Listener;

use Lmc\Steward\Test\AbstractTestCase;
use Nette\Utils\Strings;

/**
 * Listener to take snapshots of the page (screenshot and html snapshot) on each error or failure.
 */
class SnapshotListener extends \PHPUnit_Framework_BaseTestListener
{
    public function addError(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
        $this->takeSnapshot($test, $e, $time);
    }

    public function addFailure(\PHPUnit_Framework_Test $test, \PHPUnit_Framework_AssertionFailedError $e, $time)
    {
        $this->takeSnapshot($test, $e, $time);
    }

    /**
     * Take screenshot and save it.
     * @param AbstractTestCase $test
     * @param \Exception $e
     * @param $time
     */
    private function takeSnapshot(AbstractTestCase $test, \Exception $e, $time)
    {
        $savePath = LOGS_DIR . '/';

        $testIdentifier = Strings::webalize(get_class($test), null, $lower = false)
            . '-'
            . Strings::webalize($test->getName(), null, $lower = false)
            . '-'
            . date('Y-m-d-H-i-s');

        if (!$test->wd instanceof \RemoteWebDriver) {
            $test->warn('WebDriver instance not found, cannot take screenshot.');
            return;
        }

        $test->log('Taking snapshots of page "%s" because: "%s"', $test->wd->getCurrentURL(), $e->getMessage());

        // Save PNG screenshot
        $screenshotPath = $savePath . $testIdentifier . '.png';
        $test->wd->takeScreenshot($screenshotPath);
        $test->appendTestLog('Screenshot saved to file "%s" ', $test->prependArtifactUrl($screenshotPath));

        // Save HTML snapshot of page
        $htmlPath = $savePath . $testIdentifier . '.html';
        file_put_contents($htmlPath, $test->wd->getPageSource());
        $test->appendTestLog('HTML snapshot saved to file "%s" ', $test->prependArtifactUrl($htmlPath));
    }
}

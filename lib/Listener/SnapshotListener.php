<?php

namespace Lmc\Steward\Listener;

use Lmc\Steward\Test\AbstractTestCase;
use Lmc\Steward\Test\ConfigProvider;
use Nette\Utils\Strings;

/**
 * Listener to take snapshots of the page (screenshot and html snapshot) on each error or failure.
 */
class SnapshotListener extends \PHPUnit_Framework_BaseTestListener
{
    public function addError(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
        $this->takeSnapshot($test);
    }

    public function addFailure(\PHPUnit_Framework_Test $test, \PHPUnit_Framework_AssertionFailedError $e, $time)
    {
        if ($test instanceof \PHPUnit_Framework_Warning) {
            return;
        }

        $this->takeSnapshot($test);
    }

    /**
     * Take screenshot and save it.
     *
     * @param AbstractTestCase $test
     */
    protected function takeSnapshot(AbstractTestCase $test)
    {
        $savePath = ConfigProvider::getInstance()->getConfig()->logsDir . '/';

        $testIdentifier = Strings::webalize(get_class($test), null, $lower = false)
            . '-'
            . Strings::webalize($test->getName(), null, $lower = false)
            . '-'
            . date('Y-m-d-H-i-s');

        if (!$test->wd instanceof \RemoteWebDriver) {
            $test->warn('WebDriver instance not found, cannot take screenshot.');
            return;
        }

        $test->appendTestLog(
            'Test failed on page "%s", taking page snapshots:',
            $test->wd->getCurrentURL()
        );

        // Save PNG screenshot
        $screenshotPath = $savePath . $testIdentifier . '.png';
        $test->wd->takeScreenshot($screenshotPath);
        $test->appendTestLog('Screenshot saved to file "%s" ', $this->getSnapshotUrl($screenshotPath));

        // Save HTML snapshot of page
        $htmlPath = $savePath . $testIdentifier . '.html';
        file_put_contents($htmlPath, $test->wd->getPageSource());
        $test->appendTestLog('HTML snapshot saved to file "%s" ', $this->getSnapshotUrl($htmlPath));
    }

    /**
     * Get url based on relative path of specific snapshot.
     * In our implementation we prepend artifact's URL to given relative path to make it clickable in Jenkins output.
     *
     * @param $path
     * @return string
     */
    protected function getSnapshotUrl($path)
    {
        if (getenv('JENKINS_URL') && getenv('BUILD_URL') && getenv('WORKSPACE')) {
            $realPath = realpath($path);
            if ($realPath) {
                // from absolute path, remove workspace
                $path = str_replace(getenv('WORKSPACE'), '', $realPath);
                // prepend url to artifact
                $path = getenv('BUILD_URL') . "artifact/" . $path;
            }
        }

        return $path;
    }
}

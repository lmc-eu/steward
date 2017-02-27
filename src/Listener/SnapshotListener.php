<?php

namespace Lmc\Steward\Listener;

use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Test\AbstractTestCase;
use Nette\Utils\Strings;
use PHPUnit\Framework\BaseTestListener;

/**
 * Listener to take snapshots of the page (screenshot and html snapshot) on each error or failure.
 */
class SnapshotListener extends BaseTestListener
{
    public function addError(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {
        if ($test instanceof AbstractTestCase) {
            $this->takeSnapshot($test);
        }
    }

    public function addFailure(\PHPUnit_Framework_Test $test, \PHPUnit_Framework_AssertionFailedError $e, $time)
    {
        if ($test instanceof AbstractTestCase) {
            $this->takeSnapshot($test);
        }
    }

    /**
     * Take screenshot and HTML snapshot of the page and save it.
     *
     * @param AbstractTestCase $test
     */
    protected function takeSnapshot(AbstractTestCase $test)
    {
        if (!$test->wd instanceof RemoteWebDriver) {
            $test->appendTestLog('[WARN] WebDriver instance not found, cannot take snapshot.');

            return;
        }

        $savePath = ConfigProvider::getInstance()->logsDir . '/';
        $testIdentifier = $this->assembleTestIdentifier($test);

        try {
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
        } catch (WebDriverException $e) {
            $test->appendTestLog('[WARN] Error taking page snapshot, perhaps browser is not accessible?');

            return;
        }
    }

    /**
     * Get url based on relative path of specific snapshot.
     * In our implementation we prepend artifact's URL to given relative path to make it clickable in Jenkins output.
     *
     * @param string $path
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
                $path = getenv('BUILD_URL') . 'artifact/' . $path;
            }
        }

        return $path;
    }

    /**
     * @param AbstractTestCase $testCase
     * @return string
     */
    private function assembleTestIdentifier(AbstractTestCase $testCase)
    {
        return sprintf(
            '%s-%s-%s',
            Strings::webalize(get_class($testCase), null, $lower = false),
            Strings::webalize($testCase->getName(), null, $lower = false),
            date('Y-m-d-H-i-s')
        );
    }
}

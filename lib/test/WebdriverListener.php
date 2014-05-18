<?php

namespace Lmc\Steward\Test;

/**
 * This is a workaround for the order in which PHPUnit executes addFailure on listeners and tearDown
 * If taking screenshot using addFailure, tearDown was already called and the browser closed
 */
class WebdriverListener extends \PHPUnit_Framework_BaseTestListener
{
    public function startTest(\PHPUnit_Framework_Test $test)
    {
        if (!$test instanceof AbstractTestCase) {
            throw new \InvalidArgumentException('Test case must be descendant of Lmc\Steward\Test\AbstractTestCase');
        }

        $test->log(
            sprintf('Initializing "%s" webdriver for "%s::%s"', BROWSER_NAME, get_class($test), $test->getName())
        );

        $capabilities = [
            \WebDriverCapabilityType::BROWSER_NAME => BROWSER_NAME,
        ];

        if (BROWSER_NAME == 'internet explorer') {
            // When set to true, this capability clears the cache, cookies, history, and saved form data.
            $capabilities['ie.ensureCleanSession'] = true;
        }

        $test->wd = \RemoteWebDriver::create(SERVER_URL .  '/wd/hub', $capabilities, $timeoutInMs = 2*60*1000);
    }

    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
        if (!$test instanceof AbstractTestCase) {
            throw new \InvalidArgumentException('Test case must be descendant of Lmc\Steward\Test\AbstractTestCase');
        }

        if ($test->wd instanceof \RemoteWebDriver) {
            $test->log(
                sprintf(
                    'Destroying "%s" webdriver for "%s::%s" (session %s)',
                    BROWSER_NAME,
                    get_class($test),
                    $test->getName(),
                    $test->wd->getSessionID()
                )
            );

            // Necessary for phantomjs - see https://github.com/detro/ghostdriver/issues/343
            $test->wd->getCommandExecutor()->execute('deleteAllCookies');

            $test->wd->close();
            $test->wd->quit();
        }
    }
}

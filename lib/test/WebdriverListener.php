<?php

/**
 * This is a workaround for the order in which PHPUnit executes addFailure on listeners and tearDown
 * If taking screenshot using addFailure, tearDown was already called and the browser closed
 */
class WebdriverListener extends PHPUnit_Framework_BaseTestListener {

    public function startTest(PHPUnit_Framework_Test $test)
    {
        if (!$test instanceof Lmc\Steward\Test\AbstractTestCase) {
            throw new InvalidArgumentException('Test case must be descendant of Lmc\Steward\Test\AbstractTestCase');
        }

        $test->log(sprintf('Initializing "%s" webdriver for "%s::%s"', BROWSER_NAME, get_class($test), $test->getName()));

        $capabilities = [
            \WebDriverCapabilityType::BROWSER_NAME => BROWSER_NAME
        ];

        $test->wd = \RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);

    }

    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
        if (!$test instanceof Lmc\Steward\Test\AbstractTestCase) {
            throw new InvalidArgumentException('Test case must be descendant of Lmc\Steward\Test\AbstractTestCase');
        }

        $test->log(sprintf('Destroying "%s" webdriver for "%s::%s"', BROWSER_NAME, get_class($test), $test->getName()));

        if ($test->wd instanceof \RemoteWebDriver) {
            $test->wd->close();
            $test->wd->quit();
        }
    }
}

# Steward: easy and robust testing with Selenium WebDriver + PHPUnit

[![Latest Stable Version](https://img.shields.io/packagist/v/lmc/steward.svg?style=flat-square)](https://packagist.org/packages/lmc/steward)
[![GitHub Actions Build Status](https://img.shields.io/github/workflow/status/lmc-eu/steward/Tests%20and%20linting?style=flat-square&label=GitHub%20Actions%20build)](https://github.com/lmc-eu/steward/actions)
[![AppVeyor Build Status](https://img.shields.io/appveyor/ci/lmc-eu/steward/main.svg?style=flat-square&label=AppVeyor)](https://ci.appveyor.com/project/lmc-eu/steward)
[![Coverage Status](https://img.shields.io/coveralls/lmc-eu/steward/main.svg?style=flat-square)](https://coveralls.io/r/lmc-eu/steward?branch=main)
[![Total Downloads](https://img.shields.io/packagist/dt/lmc/steward.svg?style=flat-square)](https://packagist.org/packages/lmc/steward)

Steward is a set of libraries made to simplify writing and running robust functional system tests in
[PHPUnit](https://phpunit.de/) using [Selenium WebDriver](https://www.selenium.dev/).

## What's great about Steward?
- It allows you to start writing complex test cases in a minute.
- It performs a lot of work for you:
   - downloads and installs Selenium server with one command
   - sets-up browser of your choice
   - automatically takes a screenshot on failed assertions
   - produces test results in JUnit format (easily processable, for example, by Jenkins and other tools)
   - and more...
- Tests are run in parallel, so the only bottleneck is the number of Selenium nodes you start simultaneously.
- Simple syntax sugar layer on top of default [WebDriver commands](https://github.com/php-webdriver/php-webdriver/wiki/Example-command-reference) helps shorten your tests and improve readability.
- If you already use PHP, you don't have to learn a new language to write functional tests. Moreover, if you are familiar with unit tests and PHPUnit, you know it all.
- Allows you to plan test dependencies.
   - For example, if you need to wait 2 minutes until some event gets through your message queue before testing the result? No problem! The order of tests is optimized to minimize the total execution time.
- Status of tests can be clearly watched during test execution, so you will easily know how many tests have finished and what their results are.
- You can extend Steward easily by registering custom events to EventDispatcher.
   - For example if you can add custom configuration options or change parameters passed to child PHPUnit processes.
- Cloud services like [Sauce Labs](https://saucelabs.com/), [BrowserStack](https://www.browserstack.com/) or [TestingBot](https://testingbot.com/) are fully integrated giving you a chance to run tests with less setup and without your own infrastructure.
- Steward is field tested - we use it daily in [our company](https://www.lmc.eu/en/) to maintain the quality of our products thanks to hundreds of test-cases. The library itself is also extensively covered by unit tests.
- Steward is built on solid foundations: [WebDriver](https://www.w3.org/TR/webdriver/) is W3C draft standard for browser automation,
[php-webdriver](https://github.com/php-webdriver/php-webdriver) is the most used and developed Selenium language binding for PHP,
[PHPUnit](https://phpunit.de/) is a well known and widely used testing framework, and
[Symfony Console](https://symfony.com/doc/current/components/console.html) is industry standard for PHP CLI applications.

## Example usage
To see how to use and extend Steward, have a look at our [example project](https://github.com/lmc-eu/steward-example).

## Changelog
For the latest changes see the [CHANGELOG.md](./CHANGELOG.md) file. We follow [Semantic Versioning](https://semver.org/).

## Getting started
### 1. Install Steward
For most cases we recommend having functional tests in the same repository as your application but in a separate folder.
We suggest putting them in a `selenium-tests/` directory.

**In this directory**, simply install Steward with the following command:

```sh
$ composer require lmc/steward
```

**Note:** you will need to have [Composer](https://getcomposer.org/) installed to do this.

### 2. Download Selenium Server and browser drivers
The following step only applies if you want to download and run Selenium Standalone Server with the test browser locally right on your computer.
Another possibility is to [start Selenium Server and test browser inside a Docker container][wiki-docker].

#### Get Selenium Standalone Server
You need to download Selenium server so it can execute commands in the specified browser.
In the root directory of your tests (e.g. `selenium-tests/`)  simply run:

```sh
$ ./vendor/bin/steward install
```

This will check for the latest version of Selenium Standalone Server and download it for you (the jar file will
be placed into the `./vendor/bin` directory).

You may want to run this command as part of your CI server build, then simply use the `--no-interaction` option to
download Selenium without any interaction and print the absolute path to the jar file as the sole output.

#### Download browser drivers
If it is not already installed on your system, you will need to download Selenium driver for the browser(s) you want to
use for the tests. See [Selenium server & browser drivers][wiki-drivers]
in our wiki for more information.

### 3. Write the first test
To provide you with Steward functionality, your tests have to extend the `Lmc\Steward\Test\AbstractTestCase` class.

You must also configure [PSR-4 autoloading](https://www.php-fig.org/psr/psr-4/) so that your tests could be found by
Steward. It is as easy as adding the following to your `composer.json` file:

```json
    "autoload": {
        "psr-4": {
            "My\\": "tests/"
        }
    }
```
Don't forget to create the `selenium-tests/tests/` directory and to run `composer dump-autoload` afterwards.

For the test itself, place it in the `selenium-tests/tests/` directory:

```php
<?php
// selenium-tests/tests/TitlePageTest.php

namespace My; // Note the "My" namespace maps to the "tests" folder, as defined in the autoload part of `composer.json`.

use Facebook\WebDriver\WebDriverBy;
use Lmc\Steward\Test\AbstractTestCase;

class TitlePageTest extends AbstractTestCase
{
    public function testShouldContainSearchInput()
    {
        // Load the URL (will wait until page is loaded)
        $this->wd->get('https://www.w3.org/'); // $this->wd holds instance of \RemoteWebDriver

        // Do some assertion
        $this->assertContains('W3C', $this->wd->getTitle());

        // You can use $this->log(), $this->warn() or $this->debug() with sprintf-like syntax
        $this->log('Current page "%s" has title "%s"', $this->wd->getCurrentURL(), $this->wd->getTitle());

        // Make sure search input is present
        $searchInput = $this->wd->findElement(WebDriverBy::cssSelector('#search-form input'));
        // Or you can use syntax sugar provided by Steward (this is equivalent of previous line)
        $searchInput = $this->findByCss('#search-form input');

        // Assert title of the search input
        $this->assertEquals('Search', $searchInput->getAttribute('title'));
    }
}

```

### 4. Run your tests
#### Start Selenium server
Now you need to start Selenium server, which will listen for and execute commands sent from your tests.

```sh
$ java -jar ./vendor/bin/selenium-server-standalone-3.4.0.jar # the version may differ
```

This will start a single Selenium Server instance (listening on port 4444) in "no-grid" mode (meaning the server receives
and executes the commands itself).

**Note:** You may want to run Selenium  Server in a grid mode. This has the *hub* receiving commands while multiple *nodes* execute them. 
Consult --help and the `-role` option of Selenium server.

#### Run Steward!
Now that Selenium Server is listening, let's launch your test! Use the  `run` command:

```sh
./vendor/bin/steward run staging firefox
```

In a few moments you should see a Firefox window appear, then the https://www.w3.org/ site (as defined in the example tests)
should be loaded before the window instantly closes. See the output of the command to check the test result.

The `run` command has two required arguments: the name of the environment and the browser:
- The environment argument has no effect by default, but is accessible in your tests making it easy to, for example, change the base URL of your tested site. This would be useful for testing between your local server and staging environments
- The browser name could be any browser name supported by Selenium. Most common are "firefox", "chrome", "phantomjs", "safari" and "internet explorer". See our wiki for more info related to [installing browser drivers][wiki-drivers].

There is also a bunch of useful options for the `run` command:

- `--group` - only run specific group(s) of tests
- `--exclude-group` - exclude some group(s) of tests (can be even combined with `--group`)
- `--server-url` - set different url of selenium server than the default (which is `http://localhost:4444/wd/hub`)
- `--xdebug` - start Xdebug debugger on your tests. Allows you to debug tests from your IDE ([learn more about tests debugging][wiki-debugging] in our Wiki)
- `--capability` - directly pass any extra capability to the Selenium WebDriver server ([see wiki][wiki-capabilities] for more information and examples)
- `--parallel-limit` - limit number of testcases being executed in a parallel (default is 50)
- `--help` - see all other options and default values
- **adjust output levels:** by default, only the test results summary is printed to the output; the verbosity could be changed by the following:
    - `-v` - to instantly output name of failed test(s)
    - `-vv` - also print progress information during run (which tests were started/finished etc); if any test fails, its output will by printed to the console
    - `-vvv` - output everything, including all output from the tests

### 5. See the results and screenshots
The log is printed to the console where you run the `run` command. This could be a bit confusing, especially if you run multiple tests in parallel.

As a solution, for each testcase there is a separate file in JUnit XML format, placed in `logs/` directory. Screenshots and HTML snapshots are also saved into this directory (they are automatically generated on failed assertion or if a WebDriver command fails).

To see the current status of tests during (or after) test execution, open the `logs/results.xml` file in your browser:

![Example output as displayed in logs/results.xml file](https://lmc-eu.github.io/steward/images/results-output-example.png)

Similar output in the command line interface can be obtained using the `./vendor/bin/steward results` command (see below). You can also add `-vvv` to see results of each individual test.

![Example output of results command](https://lmc-eu.github.io/steward/images/results-output-cli.png)

### 6. See test execution timeline
Steward provides a visual representation of the test execution timeline. When used with Selenium Server in "grid" mode you can see which
Selenium node executed which testcase, identify possible bottlenecks and so on.

To generate the timeline, simply run the `generate-timeline` command after your test build is finished:

```sh
./vendor/bin/steward generate-timeline
```

File `timeline.html` will then be generated into the `logs/` directory.

![Example timeline visualization](https://lmc-eu.github.io/steward/images/timeline.png)

## License
Steward is open source software licensed under the [MIT license](./LICENCE.md).

[wiki-docker]: https://github.com/lmc-eu/steward/wiki/Selenium-server-&-browser-drivers#option-2-start-selenium-server--browser-inside-docker-
[wiki-debugging]: https://github.com/lmc-eu/steward/wiki/Debugging-Selenium-tests-with-Steward
[wiki-capabilities]: https://github.com/lmc-eu/steward/wiki/Set-custom-capabilities
[wiki-drivers]: https://github.com/lmc-eu/steward/wiki/Selenium-server-&-browser-drivers#2-install-browser-driver

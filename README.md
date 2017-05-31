# Steward: easy and robust testing with Selenium WebDriver + PHPUnit

[![Latest Stable Version](https://img.shields.io/packagist/v/lmc/steward.svg?style=flat-square)](https://packagist.org/packages/lmc/steward)
[![Travis Build Status](https://img.shields.io/travis/lmc-eu/steward.svg?style=flat-square)](https://travis-ci.org/lmc-eu/steward)
[![AppVeyor Build Status](https://img.shields.io/appveyor/ci/lmc-eu/steward.svg?style=flat-square)](https://ci.appveyor.com/project/lmc-eu/steward)
[![Coverage Status](https://img.shields.io/coveralls/lmc-eu/steward/master.svg?style=flat-square)](https://coveralls.io/r/lmc-eu/steward?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/lmc/steward.svg?style=flat-square)](https://packagist.org/packages/lmc/steward)
[![License](https://img.shields.io/packagist/l/lmc/steward.svg?style=flat-square)](https://packagist.org/packages/lmc/steward)

Steward is set of libraries made to simplify writing and running robust functional system tests in
[PHPUnit](https://phpunit.de/) using [Selenium WebDriver](http://www.seleniumhq.org/).

## What's great about Steward?
- It allows you to start writing complex test cases in a minute.
- It makes a lot of work for you: download and install Selenium server with one command; set-up browser of your choice; automatically take a screenshot on failed assertion; produce test results in JUnit format (easily processable e.g. by Jenkins and other tools) and more.
- Your tests are run in a parallel, so the bottleneck is just the amount of Selenium nodes you start simultaneously.
- Simple syntax sugar layer on top of default [WebDriver commands](https://github.com/facebook/php-webdriver/wiki/Example-command-reference) can help you shorten your tests and make them more readable.
- If you already use PHP, you don't have to learn a new language to write functional tests. Moreover, if you are familiar with unit tests and PHPUnit, you know it all.
- Allows you to plan tests dependencies - need to wait 2 minutes until some event gets through your message queue so you could test the result? No problem! The order of tests is even optimized to minimize the total execution time.
- Status of the tests could be clearly watched during tests execution, so you will easily know how many tests were already finished and what was their result.
- You can extend it easily by e.g. registering custom events to EventDispatcher. Thus you can, for example, add custom configuration options or change parameters passed to child PHPUnit processes.
- Cloud services like [Sauce Labs](https://saucelabs.com/), [BrowserStack](https://www.browserstack.com/) or [TestingBot](https://testingbot.com/) are fully integrated, giving you a possibility to run tests with even less setup and without own infrastructure.
- It is field tested - we use it daily in [our company](https://www.lmc.eu/english) to maintain quality of our various products thanks to hundreds of test-cases. The library itself is also extensively covered with unit tests.
- Steward is built on solid foundations: [WebDriver](http://www.w3.org/TR/webdriver/) is W3C draft standard for browser automation,
[php-webdriver](https://github.com/facebook/php-webdriver) is the most used and developed Selenium language binding for PHP,
[PHPUnit](https://phpunit.de/) is well known and widely used testing framework and
[Symfony Console](http://symfony.com/doc/current/components/console.html) is industry standard for PHP CLI applications.

## Example usage
To see how to use and extend Steward, have a look at our [example project](https://github.com/lmc-eu/steward-example).

## Changelog
For latest changes see [CHANGELOG.md](CHANGELOG.md) file. We follow [Semantic Versioning](http://semver.org/).

## Getting started
### 1. Install Steward
For most cases we recommend having functional tests in the same repository as your application but in a separate folder.
So let's suggest we put them in `selenium-tests/` directory.

**In this directory** then simply install Steward:

```sh
$ composer require lmc/steward
```

Note you will need to have [Composer](https://getcomposer.org/) installed to do this.

### 2. Download Selenium server and browser driver
You can download and run Selenium standalone server and the browser locally right on your computer.
Another possibility is to [start Selenium server + browser inside Docker container][wiki-docker].
To run Selenium locally:

#### Get Selenium standalone server
You need to download Selenium server so it can execute commands in the specified browser.
In the root directory of your tests (e.g. `selenium-tests/`)  simply run:

```sh
$ ./vendor/bin/steward install
```

This will check for the latest released version of Selenium standalone server and download it for you (the jar file will
be placed into `./vendor/bin` directory).

You may want to run this command as part of your CI server build - then simply use the `--no-interaction` option to
download the Selenium without any interaction and print absolute path to the jar file as the sole output.

#### Download browser driver
If it is not already installed on your system, you will need to download Selenium driver for the browser you want to
use for the tests. See dedicated page [Selenium server & browser drivers][wiki-drivers]
in our wiki for more information.

### 3. Write the first test
To provide you the Steward functionality, your tests have to extend the `Lmc\Steward\Test\AbstractTestCase` class.

You must also configure [PSR-4 autoloading](http://www.php-fig.org/psr/psr-4/) so that your tests could be found by
Steward. For the following example it is as easy as adding following to your `composer.json`:

```json
    "autoload": {
        "psr-4": {
            "My\\": "tests/"
        }
    }
```
Don't forget to create the `selenium-tests/tests/` directory and to run `composer dump-autoload` afterward.

Now the test itself (place it to `selenium-tests/tests/` directory):

```php
<?php
// selenium-tests/tests/TitlePageTest.php

namespace My; // Note the "My" namespace maps to the "tests" folder, as defined in the autoload part of `composer.json`.

use Lmc\Steward\Test\AbstractTestCase;

class TitlePageTest extends AbstractTestCase
{
    public function testShouldContainSearchInput()
    {
        // Load the URL (will wait until page is loaded)
        $this->wd->get('http://www.w3.org/'); // $this->wd holds instance of \RemoteWebDriver

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
Now you need to start Selenium server, which will listen and execute commands sent from your tests.

```sh
$ java -jar ./vendor/bin/selenium-server-standalone-3.4.0.jar # the version may differ
```

This will start single Selenium server instance (listening on port 4444) in "no-grid" mode (meaning the server receives
and also executes the commands itself). You may want to run the Selenium  server in a grid mode (when the hub is
receiving the commands and multiple nodes are executing them) - please consult help and especially the `-role` option
of the Selenium server.

#### Run Steward!
Having your Selenium server listening, let's launch your test! Use the  `run` command:

```sh
./vendor/bin/steward run staging firefox
```

In few moments you should see Firefox window appearing, then the http://www.w3.org/ site (as defined in the example tests)
should be loaded and the window will be instantly closed. See the output of the command to check the test result.

The `run` command has two required arguments - the name of environment and browser:
- The environment argument has no effect by default, but it is accessible in your tests making it easy to e.g. change the base URL of your tested site - for example, your local server or staging environment
- The browser name could be any name of browser supported by Selenium. Most common are "firefox", "chrome", "phantomjs", "safari" and "internet explorer". See our wiki for more info related to [installing browser drivers][wiki-drivers].

There is also a bunch of useful options of `run` command:

- `--group` - run just specific group(s) of tests
- `--exclude-group` - exclude some group(s) of tests (can be even combined with `--group`)
- `--server-url` - set different url of selenium server than the default (http://localhost:4444)
- `--xdebug` - start Xdebug debugger on your tests, so you can debug tests from your IDE ([learn more about tests debugging][wiki-debugging] in our Wiki)
- `--capability` - directly pass any extra capability to the Selenium WebDriver server ([see wiki][wiki-capabilities] for more information and examples)
- `--help` - see all other options and default values
- **adjust output levels:** by default, only test results summary is printed to the output; the verbosity could be changed like this:
    - `-v` - to instantly output name of failed test(s)
    - `-vv` - print also progress information during run (which tests were started/finished etc); if any test fails, its output will by printed to the console
    - `-vvv` - output everything, including all output from the tests

### 5. See the results and screenshots
The log is printed to the console where you run the `run` command. But this could be a bit confusing, especially if you run multiple tests in parallel.

So for each testcase there is a separate file in JUnit XML format, placed in `logs/` directory. Also, screenshots and HTML snapshots are saved into this directory (they are automatically generated on failed assertion or if some WebDriver command fails).

To see the current status of tests during (or after) tests execution, open file `logs/results.xml` in your browser:

![Example output as displayed in logs/results.xml file](https://lmc-eu.github.io/steward/images/results-output-example.png)

Similar output but in command line interface could be obtained using `steward results` command - see below. You can also add `-vvv` to see results of each individual test.

![Example output of results command](https://lmc-eu.github.io/steward/images/results-output-cli.png)

### 6. See test execution timeline
Steward provides a visual representation of test execution timeline. When used with Selenium server grid you can see which
Selenium node executed which testcase, identify possible bottlenecks and so on.

To generate the timeline, simply run after your test build is finished the command `generate-timeline`:

```sh
./vendor/bin/steward generate-timeline
```

File `timeline.html` will be then generated into `logs/` directory.

![Example timeline visualization](https://lmc-eu.github.io/steward/images/timeline.png)

## License
Steward is open source software licensed under the [MIT license](LICENCE.md).

[wiki-docker]: https://github.com/lmc-eu/steward/wiki/Selenium-server-&-wiki-drivers#option-2-start-selenium-server--browser-inside-docker-
[wiki-debugging]: https://github.com/lmc-eu/steward/wiki/Debugging-Selenium-tests-with-Steward
[wiki-capabilities]: https://github.com/lmc-eu/steward/wiki/Set-custom-capabilities
[wiki-drivers]: https://github.com/lmc-eu/steward/wiki/Selenium-server-&-wiki-drivers#2-install-browser-driver

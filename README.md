# Steward: easy and robust testing with Selenium WebDriver + PHPUnit

[![Build Status](https://img.shields.io/travis/lmc-eu/steward.svg?style=flat-square)](https://travis-ci.org/lmc-eu/steward) 
[![Coverage Status](https://img.shields.io/coveralls/lmc-eu/steward/master.svg?style=flat-square)](https://coveralls.io/r/lmc-eu/steward?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/lmc/steward.svg?style=flat-square)](https://packagist.org/packages/lmc/steward)
[![License](https://img.shields.io/github/license/lmc-eu/steward.svg?style=flat-square)](https://packagist.org/packages/lmc/steward) 
[![Latest Stable Version](https://img.shields.io/packagist/v/lmc/steward.svg?style=flat-square)](https://packagist.org/packages/lmc/steward)


Steward is set of libraries made to simplify writing and running robust functional system tests in
[PHPUnit](https://phpunit.de/) using [Selenium WebDriver](http://www.seleniumhq.org/). 

## [We](http://www.lmc.eu/english) use Steward, and why could you?
- It allows you to start writing complex test cases in a minute.
- It makes a lot of work for you: download and install Selenium server with one command; automatically take screenshot on failed assertion; produce test results in JUnit format (easily processable e.g. by Jenkins and other tools) and more!
- Simple syntax sugar layer on top of default [WebDriver commands](https://github.com/facebook/php-webdriver/wiki/Example-command-reference) can help you shorten your tests and make them more readable.
- Allows you to plan tests dependencies - need to wait 2 minutes until some event gets through your message queue so you could test the result? No problem! The tests order is even optimized to minimize the total execution time.
- Your tests are run in a parallel, so the bottleneck is just the amount of Selenium nodes you start simultaneously. 
- If you already use PHP, you don't have to learn a new language to write functional tests. Moreover, if you are familiar with unit tests and PHPUnit, you know it all.
- You can extend it easily by e.g. registering custom events to EventDispatcher. Thus you can for example add custom configuration options or change parameters passed to PHPUnit processes.
- Status of the tests could be clearly watched during tests execution, so you will easily know, how many test were already finished and what was their result.
- It is field tested - we use it daily in our company to maintain quality of our various products thanks to hundreds of test-cases. The library itself is also extensively covered with unit tests.
- Steward is built on solid foundations: [WebDriver](http://www.w3.org/TR/webdriver/) is W3C draft standard for browser browser automation,
[php-webdriver](https://github.com/facebook/php-webdriver) is the most used and developed Selenium language binding for PHP,
[PHPUnit](https://phpunit.de/) is well known and widely used testing framework and
[Symfony Console](http://symfony.com/doc/current/components/console/introduction.html) is industry standard for PHP CLI applications.

## Example usage
To see how to use and extend Steward, have a look at our [example project](https://github.com/lmc-eu/steward-example).

## Changelog
For latest changes see [CHANGELOG.md](CHANGELOG.md) file. We follow [Semantic Versioning](http://semver.org/).

## Getting started
### 1. Install Steward
We recommend to have functional tests in the same repository as your application.
So let's suggest we put them in `selenium-tests/` directory. **In this directory** create a new composer.json file
(you can use `composer init` command). You will need to have [Composer](http://getcomposer.org/) installed to do this.

Then simply install Steward and add it as a dependency:

```sh
$ composer require lmc/steward
```

Next necessary step is to create `tests/` and `logs/` directory inside the `selenium-tests/` directory.

### 2. Install Selenium
You need Selenium server installed to execute commands in the specified browser.
In the root directory of your tests (e.g. `selenium-tests/`)  simply run:
 
```sh
$ ./vendor/bin/steward install
```

This will check for the latest released version of Selenium standalone server and download it for you (the jar file will
be placed into `./vendor/bin` directory).

You may want to run this command as part of your CI server build - then simply use the `--no-interaction` option to
download the Selenium without any interaction and print absolute path to the jar file as the sole output.

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
Don't forget to run `composer dump-autoload` afterwards.

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
        $searchInput = $this->wd->findElement(\WebDriverBy::cssSelector('#search-form input'));
        // Or you can use syntax sugar provided by Steward (this is equivalent of previous line)
        $searchInput = $this->findByCss('#search-form input');

        // Assert title of the search input
        $this->assertEquals('Search', $searchInput->getAttribute('title'));
    }
}

```

### 4. Run your tests
#### Start Selenium server
Now you need to start Selenium server, which will listen and execute commands send from your tests.

```sh
$ java -jar ./vendor/bin/selenium-server-standalone-2.45.0.jar # the version may differ
```

This will start single Selenium server instance (listening on port 4444) in "no-grid" mode (meaning the server receives 
and also executes the commands itself). You may want to run the Selenium  server in a grid mode (when the hub is 
receiving the commands and multiple nodes are executing them) - please consult help and especially the `-role` option
of the Selenium server.

#### Run Steward!
Having your Selenium server listening, let's launch your test! Use the  `run-tests` command:

```sh
./vendor/bin/steward run-tests staging firefox
```

In few moments you should see Firefox window appearing, then the http://www.w3.org/ site (as defined in the example tests)
should be loaded and the window will be instantly closed. See output of the command to check the test result.

The `run-tests` command has two required arguments - the name of environment and browser:
- The environment argument has no effect by default, but it is accessible in your tests making it easy to e.g. change the base URL of your tested site - for example your local server or staging environment
- The browser name could be any name of browser supported by Selenium. Most common are "firefox", "chrome", "phantomjs", "safari" and "internet explorer". Except Firefox some additional steps are needed to run tests in specified browser.

There is also bunch of useful options of `run-tests` command:

- `-vvv` - enable verbose (debug) mode
- `--group` - run just specific group(s) of tests
- `--exclude-group` - exclude some group(s) of tests (can be even combined with `--group`)
- `--server-url` - set different url of selenium server than the default (http://localhost:4444)
- `--xdebug` - start Xdebug debugger on your tests, so you can debug tests from your IDE
- `--help` - see all other options and default values

### 5. See the results and screenshots
The log is printed to the console where you run the `run-tests` command. But this could be a bit confusing, especially if you run multiple tests in parallel. 

So for each testcase there is separate file in JUnit XML format, placed in `logs/` directory. Also screenshots and HTML snapsnots are saved into this directory (they are automatically generated on failed assertion or if some WebDriver command fails).

During the tests execution check file `logs/results.xml` to see current status of tests:
![Example output as displayed in logs/results.xml file](https://lmc-eu.github.io/steward/images/results-output-example.png)

## License
Steward is open source software licensed under the [MIT license](LICENCE.md).

# Changelog

<!-- There is always Unreleased section on the top. Subsections (Added, Changed, Fixed, Removed) should be added as needed. -->

## Unreleased

## 3.0.0 - 2021-12-02

### Added
- `--parallel-limit` (`-l`) option of `run` command to allow limiting maximum number of tests being run simultaneously.
- Show test duration in timeline tooltips.
- PHP 8 support.

### Changed
- Require PHP 7.3+ and Symfony 5 components.
- Update to namespaced PHPUnit. Set minimal required version to PHPUnit 8.5.
- Use php-webdriver 1.8+ with W3C WebDriver support.
- Methods now uses strict type-hints and return type-hints. Inherited classes and interfaces (eg. `CustomCapabilitiesResolverInterface`, `OptimizeOrderInterface` etc.) may require to be changed in accordance with this.
- Simplified and improved test output.
- `RunTestsProcessEvent` (dispatched from `run` command when initializing PHPUnit processes) now contains array of environment variables instead of ProcessBuilder. Use `setEnvironmentVars()` method to change the variables passed to the process.
- Default browser size is now defined using class constants instead of class variables. To override the default, instead of `public static $browserWidth = ...;` use `public const BROWSER_WIDTH = ...;`.
- When test class constants defining default browser width (`BROWSER_WIDTH`) or height (`BROWSER_HEIGHT`) is set to `null`, no default browser window size will be set on test startup.
- Don't hardcode timezone to `Europe/Prague`. Timezone is now used based on your PHP settings ([date.timezone](https://www.php.net/manual/en/datetime.configuration.php#ini.date.timezone)).
- Server URL now must be provided including URL prefix (if it has one, like `/wd/hub`) - eg. `http://foo.bar:4444/wd/hub`. This means the `/wd/hub` part is now never auto-amended.
- Package `symfony/polyfill-mbstring` now provides mbstring functions even if PHP mbstring extension is not installed.
- Read annotations (like `@group`, `@noBrowser` etc.) using different and more-robust underlying libraries.
- When filling file input element, do not dump whole file contents to the output to not pollute it with useless data.
- Download Selenium server releases from GitHub (see [announcement](https://www.selenium.dev/blog/2021/downloads-moving-to-github-releases/)).

### Fixed
- Remote server running in W3C-protocol mode (eg. Selenium v3.5.3+) was erroneously detected as BrowserStack cloud service.
- `--xdebug` option did not have any effect unless passed as the last option.
- Properly auto-detect port 443 (not 80) when https server URL is used as `--server-url`.
- Do not start browser for test skipped because it was depending on some already failed test (using `@depends` annotation).
- Parsing the latest Selenium server version in `install` command.
- Do not print `Error closing the session, browser may died.` after Firefox closes the error.
- Properly detect Xdebug 3.

### Removed
- `TestUtils` class which was already deprecated in 2.1.
    - Instead of `TestUtils::setSelect2Value()` use directly the new `Select2` component and `selectByVisiblePartialText()` method.
    - Instead of `TestUtils::getFixturePath()` use `Facebook\WebDriver\Remote\FileDetector` instead.
    - Instead of `TestUtils::sleep()` use `AbstractTestCase::sleep()` method instead.
- `getConfig()` method of ConfigProvider. Call the property instead directly on an instance of the ConfigProvider.
- `--fixtures-dir` option of the `run` command, `fixtures_dir` option of configuration file and `ConfigProvider->fixturesDir` variable. They were deprecated in version 2.1.0 and no longer used since.
- `UniqueValue` component. (You may use Faker or some other library for similar use-case.)
- `AbstractTestCaseBase` class. It should probably not affect anything, as it was only used internally.
- Workarounds for legacy Firefox (version 47 and older) which are no longer needed.
- PhantomJS support.
- `resolveRequiredCapabilities()` method from `CustomCapabilitiesResolverInterface`, as this feature is not fully used in php-webdriver anyway.

## 2.3.5 - 2020-01-20
### Changed
- Replace facebook/webdriver with its successor php-webdriver/webdriver.

## 2.3.4 - 2018-07-27
### Fixed
- Backport visibility fix of `setUp()` and `tearDown()` methods of AbstractTestCase (to eg. allow use of [lmc/coding-standard](https://github.com/lmc-eu/php-coding-standard/) in projects using Steward).

## 2.3.3 - 2018-03-12
### Fixed
- Selenium server releases were incorrectly parsed, meaning `steward install` command will detect version 3.9.1 as the latest one (even though there are already released newer versions of Selenium server).

## 2.3.2 - 2017-12-02
### Changed
- Increase minimal required version of PHPUnit and nette/reflections to maintain PHP 7.2 compatibility even with lowest possible versions of dependencies.

## 2.3.1 - 2017-10-30
### Fixed
- HTML timeline generated using `generate-timeline` command was not working properly in Safari.

## 2.3.0 - 2017-10-11
### Changed
- If url endpoint (`/wd/hub`) is passed as part of server URL, it is automatically trimmed, as it is not necessary and will cause connection error.

### Fixed
- Base directory (which affects default paths to tests, logs etc.) is now properly detected even when Steward is not installed in `/vendor` directory.
- Don't rely on default path to PHPUnit binary (`vendor/bin/phpunit`) to allow custom `bin-dir` and `vendor-dir` Composer settings.

## 2.2.1 - 2017-06-06
### Fixed
- Minor Windows compatibility issues (dir paths passed to run command now respect system directory separator etc.).
- Compatibility with Symfony/Console 3.3 (`--xdebug` option behavior was incorrect with symfony/console 3.3.0).

## 2.2.0 - 2017-05-12
### Added
- Configuration file support 🎉. Useful for global Steward configuration which doesn't change for different runs. Place `steward.yml` or `steward.yml.dist` to base directory or use `-c`/`--configuration` option to define custom path to configuration file. Supported options are currently:
    - `capabilities_resolver` (given class must implement new `CustomCapabilitiesResolverInterface`)
    - `tests_dir`
    - `logs_dir`
    - `fixtures_dir`
- Event `command.pre_initialize`, triggered before initialization of any command is started.
- Capabilities passed using `--capability` CLI option could now be forced to be specified as an string (by encapsulating the value into additional quotes).
- Print total execution time at the end; in `-vv` and `-vvv` modes print also execution after each testcase is finished.
- Show total execution of each testcase when viewing `result.xml` file via browser (currently only execution time of each test was shown). Also show full test name (including testcase name) when hovering over its name.

### Changed
- Capabilities are now resolved using `CapabilitiesResolver` class.
- Require PHPUnit ^5.7.
- Remove dependency on unmaintained Configula library (and internally reimplement configuration options retrieval).
- Improve `install` command output (eg. to always include path to downloaded file).
- Use custom method `Strings::toFilename` to convert class name to file name (and remove direct Composer dependency on `nette/utils`).

### Fixed
- Attempting to download not existing Selenium server version (with `install` command) will not create empty jar file but only show an error.
- Do not throw misleading exception "Test case must be descendant of Lmc\Steward\Test\AbstractTestCase" when invalid data provider is used.
- Debug messages about destroying WebDriver instance on the end of each test were printed to the output before output of the actual tests.
- Logs dir was not passed to PHPUnit processes, causing JUnit log files to be always written to `logs/` directory.

## 2.1.0 - 2017-01-16
### Added
- Command `generate-timeline` to generate timeline-based visualization of test run into HTML file.
- When test is started, url of the executing node is stored in the `results.xml` file. (Applies only for Selenium standalone server.)
- `Select2` component for jQuery based Select2, which mimics behavior of native `WebDriverSelect` (it actually uses the same interface; though not all methods are implemented).
- `waitForTitleRegexp()` syntax sugar method to wait until current page title matches given regular expression (shortcut for new `WebDriverExpectedCondition::titleMatches()` method).

### Changed
- Deprecate `TestUtils`. The class will be removed in next major release. This includes:
    - Deprecate default instantiation of TestUtils in TestCase - `$this->utils` property of TestCase.
    - Deprecate `TestUtils::setSelect2Value()` method. Use directly the new `Select2` component and `selectByVisiblePartialText()` method.
    - Deprecate `TestUtils::getFixturePath()` method. Use `Facebook\WebDriver\Remote\FileDetector` instead.
    - Deprecate `TestUtils::sleep()` method. Use `AbstractTestCase::sleep()` method instead.
- Upgrade [php-webdriver](https://github.com/facebook/php-webdriver) to version 1.3.0.

## 2.0.1 - 2016-12-01
### Fixed
- Values passed using `--capability` option may not work as expected for different data types than strings.

## 2.0.0 - 2016-10-30
### Removed
- Support for PHP 5.5, minimal required version of PHP is now 5.6. Also the 5.5 version is no longer supported by the upstream since July. ([#87](https://github.com/lmc-eu/steward/pull/87))
- BC: Aliases for old non-namespaced [php-webdriver](https://github.com/facebook/php-webdriver) which were deprecated in Steward 1.2. ([#66](https://github.com/lmc-eu/steward/pull/66))
- BC: `run-tests` alias of `run` command. ([#71](https://github.com/lmc-eu/steward/pull/71))
- BC: Option `--publish-results` of run command. The default publishers and custom publishers defined in phpunit.xml will be always registered. ([#85](https://github.com/lmc-eu/steward/pull/85))

### Added
- Command `results` to show test results summary from the command line (CLI equivalent to viewing `results.xml` in a browser). ([#65](https://github.com/lmc-eu/steward/pull/65))
- Command `clean` to remove old content of logs directory (previous png screenshots, HTML snapshots and JUnit xmls). ([#68](https://github.com/lmc-eu/steward/pull/68))
- Option `--capability` to `run` command which allows to simply pass any custom DesiredCapabilities to the WebDriver server. ([#78](https://github.com/lmc-eu/steward/pull/78))
- Support for cloud services like [Sauce Labs](https://saucelabs.com/), [BrowserStack](https://www.browserstack.com/) or [TestingBot](https://testingbot.com/) - simply pass remote server URL (including credentials) using `--server-url`. ([#78](https://github.com/lmc-eu/steward/pull/78), [#82](https://github.com/lmc-eu/steward/pull/82))
- If [Sauce Labs](https://saucelabs.com/) or [TestingBot](https://testingbot.com/) is used as a remote cloud platform, test results are automatically published to their API. ([#78](https://github.com/lmc-eu/steward/pull/78), [#82](https://github.com/lmc-eu/steward/pull/82))
- Microsoft Edge could be selected as a browser to run the tests. ([#95](https://github.com/lmc-eu/steward/pull/95))

### Changed
- Content of directory with logs is cleaned before `run` command starts by default. This could be suppressed using `--no-clean` option of the `run` command. ([#63](https://github.com/lmc-eu/steward/issues/63))
- Directory for logs is automatically created if it does not exist. ([#67](https://github.com/lmc-eu/steward/issues/67))
- Process output printed to console (when using `-vv` or `-vvv`) is now prefixed with class name. ([#62](https://github.com/lmc-eu/steward/issues/62), [#73](https://github.com/lmc-eu/steward/pull/73))
- BC: Pass instance of current PHPUnit test as third parameter of `publishResult()` method of `AbstractPublisher` descendants. ([#78](https://github.com/lmc-eu/steward/pull/78))
- Throw an exception if there are multiple testcase classes defined in one file. ([#79](https://github.com/lmc-eu/steward/pull/79))
- Status of tests is checked every 100 ms instead of 1 second to speed up the execution loop. ([#93](https://github.com/lmc-eu/steward/pull/93))

### Fixed
- Parsing of latest Selenium server version in `install` command so that even Selenium 3 beta releases are installed ([#76](https://github.com/lmc-eu/steward/pull/76))
- Testcases that have zero delay defined (`@delayMinutes`) weren't properly added to the dependency graph, what causes multiple bugs:
    - Testcases were not marked as failed even though the test they were depending on has failed.
    - It was not checked that the tests dependencies build a tree, ie. you could select (using `--group` etc.) subset of tests which will lead to an infinite wait in the execution loop. ([#39](https://github.com/lmc-eu/steward/issues/39))
- Output from test processes may miss is last lines if debug verbosity (`-vvv`) was enabled. ([#93](https://github.com/lmc-eu/steward/pull/93))

## 1.5.1 - 2016-08-02
### Fixed
- Parsing of latest Selenium server version in `install` command (due to Selenium 3.0.0-beta1 release). Selenium server version 2.x will be downloaded by default.

## 1.5.0 - 2016-05-05
### Added
- Shortcut `-i` to invoke `--ignore-delays` option of `run` command. ([#69](https://github.com/lmc-eu/steward/pull/69))

### Changed
- Prepare removal of deprecated features - show deprecation warning when `run-tests` alias is being used. Note this alias and also non-namespaced WebDriver (deprecated in 1.2.0) will be removed in future Steward versions! ([#60](https://github.com/lmc-eu/steward/pull/60))

## 1.4.1 - 2016-04-28
### Fixed
- Process status and result was not properly published to results.xml file. ([#59](https://github.com/lmc-eu/steward/pull/59))

## 1.4.0 - 2016-04-07
### Added
- Option `--ignore-delays` of `run` command to ignore delays defined between tests with `@delayAfter` annotation. Usable when writing or debugging dependent tests. ([#27](https://github.com/lmc-eu/steward/issues/27), [#52](https://github.com/lmc-eu/steward/pull/52))

### Changed
- Throw explanatory exception when attempting to load test file without any test class defined (instead of confusing `ReflectionException`). ([#53](https://github.com/lmc-eu/steward/pull/53))
- Throw explanatory exception when test class cannot be instantiated (if class name/namespace doesn't match file path). ([#53](https://github.com/lmc-eu/steward/pull/53))
- Testcases depending on failed testcase are instantly marked as failed and skipped. ([#47](https://github.com/lmc-eu/steward/issues/47),  [#55](https://github.com/lmc-eu/steward/pull/55))
- Allow Symfony/Process 3.0.4+ to be installed. ([#57](https://github.com/lmc-eu/steward/pull/57))

### Fixed
- Upgrade nette/reflection to not throw confusing exception if testcase does not have any use statement nor annotation. ([#51](https://github.com/lmc-eu/steward/issues/51), [#58](https://github.com/lmc-eu/steward/pull/58))

## 1.3.0 - 2016-02-26
### Added
- Provide information about results of finished processes in output of the `run` command. ([#44](https://github.com/lmc-eu/steward/pull/44))

### Changed
- Adjust output verbosity for different verbosity levels to improve clarity of the output mainly when running the tests locally. ([#45](https://github.com/lmc-eu/steward/issues/45), [#46](https://github.com/lmc-eu/steward/pull/46))
    - Use default level if you care only about test results count.
    - Use verbose mode (`-v`) to see name of failed tests during execution.
    - Use very verbose mode (`-vv`) to see detailed progress information during execution and also output of failed tests.
    - Debug level (`-vvv`) should be used when you want to know all available information about the run or when you run the tests on CI server. The tests output is printed incrementally.
- Set status of timeouted tests as done and their result as failed ([#46](https://github.com/lmc-eu/steward/pull/46))

### Fixed
- Output of tests was missing from the console when using Symfony/Process component 3.0.2. ([#48](https://github.com/lmc-eu/steward/pull/48))

## 1.2.0 - 2016-01-11
### Added
- Property annotation added to `SyntaxSugarTrait` to know `$wd` property meaning in IDE. ([#36](https://github.com/lmc-eu/steward/pull/36))

### Changed
- [php-webdriver](https://github.com/facebook/php-webdriver) upgraded to version 1.1, which moves all WebDriver classes from root namespace to `Facebook\WebDriver` namespace. Aliases for the old classes are provided in Steward 1.x, but **will be removed in future**, so you should **update your tests to use the namespaced version**. ([#31](https://github.com/lmc-eu/steward/issues/31))
- Sort testcases alphabetically so that the order is same regardless the filesystem. ([#38](https://github.com/lmc-eu/steward/pull/38))
- `run-tests` command renamed to just `run`, keeping the original name as alias to maintain backward compatibility. ([#40](https://github.com/lmc-eu/steward/pull/40))
- Upgrade to Symfony 3.0. ([#41](https://github.com/lmc-eu/steward/pull/41))
- PHPUnit 5.x could now be installed so that Steward fully supports PHP 7. ([#43](https://github.com/lmc-eu/steward/pull/43))

### Fixed
- When exception from WebDriver occurs (ie. browser dies or times out), prevent throwing another exception when attempting to closes the session (which leads to PHPUnit not generating any report). ([#7](https://github.com/lmc-eu/steward/issues/7))

## 1.1.1 - 2015-08-25
### Changed
- Require PHPUnit 4.8.6 to fix incorrect test status being reported (see [phpunit#1835](https://github.com/sebastianbergmann/phpunit/issues/1835)). ([#34](https://github.com/lmc-eu/steward/pull/34))

### Fixed
- Tests having @dataProvider and named data-sets were not properly logged with XmlPublisher and exceptions were thrown. ([#28](https://github.com/lmc-eu/steward/issues/28), [#29](https://github.com/lmc-eu/steward/pull/29))
- Start and end dates of tests were sometimes not properly displayed when viewing results.xml file in Firefox. ([#33](https://github.com/lmc-eu/steward/pull/33))

## 1.1.0 - 2015-06-09
### Added
- Check if browser given to `run-tests` command is supported (this helps avoiding typos, interchange of browser and environment etc.). ([#9](https://github.com/lmc-eu/steward/issues/9), [#15](https://github.com/lmc-eu/steward/pull/15))
- Option `--filter` which allows filtering tests/testcases by name ([#20](https://github.com/lmc-eu/steward/pull/20)). - @ziizii

### Changed
- The `logs/results.xml` could now be also accessed locally (the XSLT is now embedded right into the file, so we don't encounter same-origin policy problems). ([25d73a4](https://github.com/lmc-eu/steward/commit/25d73a4f7e4db348836c4d1f9357d734f9b1c627))
- Use tagged version 0.6.0 of [php-webdriver](https://github.com/facebook/php-webdriver). ([#11](https://github.com/lmc-eu/steward/pull/11))
- Upgrade to Symfony 2.7 (causing eg. the `--help` format to change a bit - according to [docopt](http://docopt.org/) standard) ([#18](https://github.com/lmc-eu/steward/pull/18))
- If any test fails, the `run-tests` command now exits with code `1`. This behavior could be altered using new `--no-exit` option, which forces the command to exit `0` even if some test fails. ([#13](https://github.com/lmc-eu/steward/issues/13), [#16](https://github.com/lmc-eu/steward/pull/16))
- Steward executable was renamed from `steward.php` to just `steward`, which should work on both Unix and Windows ([#21](https://github.com/lmc-eu/steward/issues/21), [#25](https://github.com/lmc-eu/steward/pull/25)) - @mhujer

### Fixed
- Properly trigger PHPUnit colored (ANSI) mode when Steward itself is in ANSI mode. ([#10](https://github.com/lmc-eu/steward/pull/10))
- Stop counting test's running time (shown in `results.xml`), if the test ended with fatal error. ([#6](https://github.com/lmc-eu/steward/issues/6), [#17](https://github.com/lmc-eu/steward/pull/17))

## 1.0.0 - 2015-05-09
### Added
- Possibility to specify zero as `@delayMinutes` (eg. if you want to force test order, but don't need to actually wait) or to use floating point number as delay.
- SyntaxSugarTrait with shortcuts for element locating and waiting (`$this->findBy...()`, `$this->findMultipleBy...()` and `$this->waitFor...()`) usable in TestCases and Components.

### Changed
- BC: Global configuration constants replaced with Config object accessed through ConfigProvider. Thus all the global constants (BROWSER_NAME, ENV, SERVER_URL, PUBLISH_RESULTS, FIXTURES_DIR, LOGS_DIR, DEBUG) are no longer available, use the Config object instead.
- BC: `lib/` and `lib-tests/` directories renamed to `src/` and `src-tests/` respectively.
- BC: Changed namespaces of eg. `ConfigProvider` and `ProcessSet`.

## 0.12.0 - 2015-02-12
### Changed
- Workaround for Firefox locking port collisions (consequence of Selenium issue [#5172](https://github.com/seleniumhq/selenium-google-code-issue-archive/issues/5172)) - if FF cannot be started, try it again up to five times

## 0.11.0 - 2015-02-06
### Added
- New `debug()` method that output to log only in debug mode. Could be used in both TestCases and Components.
- ext-curl requirement into composer.json (as CURL is needed by HttpCommandExecutor).

### Changed
- Test-cases order is optimized before running tests, so that test-cases with the longest delay of theirs dependencies are run as first.
- Improved error messages when Selenium server is not accessible or when any of required directory does not exist.

### Fixed
- Error is thrown if not-existing method is called on AbstractComponent descendants.
- Properly handle PHPUnit_Framework_Warning when eg. data provider is invalid.

## 0.10.1 - 2014-11-27
### Fixed
- Fix Xdebug being initialized even if the `--xdebug` option of `run-tests` command was not passed at all.

## 0.10.0 - 2014-11-26
### Added
- `@noBrowser` annotation on either test case class or test method could be used if you don't need to create new WebDriver (= browser window) instance.
- Legacy component (imported from separate repository). It allows you to share data between test-cases.
- Verbose output of executed WebDriver commands is provided in debug mode of `run-tests` command (debug mode could be enabled using `-vvv`).
- Added `--xdebug` option to `run-tests` command to allow simple remote debugging of tests using Xdebug.

### Changed
- Test cases running longer than 1 hour are killed and their result is set as "Fatal" in `results.xml`.

## 0.9.0 - 2014-11-20

### Added
- CHANGELOG.md file.
- Global `DEBUG` constant (is true if -vvv passed to run-tests), that could be used in tests to print some more verbose output.
- The `install` command now checks for the latest version of Selenium server and interactively asks for confirmation.
- Possibility to add custom test Publishers (enabled by `--publish-results`). Pass it as argument of TestStatusListener in `phpunit.xml`.
- During the `run-tests` execution, current test results are generated into `logs/results.xml`. This is useful eg. on long-lasting Jenkins jobs or if you want to know more precise status of the current (or last) test run.
- Shortcut to WebDriver in components - it's now possible to write `$this->wd` instead of `$this->tc->wd`.
- Possibility to set `--fixtures-dir` in `run-tests` command. This is handy eg. when tests are run on remote terminal and fixtures are located on network directory.
- Possibility to set `--logs-dir` in `run-tests` command. Useful when Steward core is installed as dependency and is run from vendor/bin/.
- Possibility to easily overload any browser-specific capability, using custom (overloaded) WebdriverListener.
- Specified group(s) could be excluded from the `run-tests`, using `--exclude-group` option.

### Changed
- The last argument of `run-tests` command (browser name) is now always required (as well as the environment name).
- The `--publish-results` parameter is now used without a value (as a switch to enable publishing test results).
- Unified testcases and test status and results naming:
    - Testcases (= process) statuses: done, prepared, queued
    - Testcases (= process) results (for "done" status): passed, failed, fatal
    - Test statuses: started, done
    - Test results (for "done" status): passed, failed, broken, skipped, incomplete
- The `install` command now outputs only full path to jar file in `-no-interactive` mode (except `-vv` or `-vvv` is passed) and nothing else.
- Path to tests in `run-tests` command is now defined using `--tests-dir`.
- Browser resolution is by default 1280x1024 and could easily be set eg. in AbstractTestCase using `$browserWidth` and `$browserHeight` properties.
- The `--group` option could now be specified multiple times to select multiple values for `run-tests`.
- Both `run-tests` and `install` commands now uses the project base directory as root path (for logs, test files, fixtures and as jar file installation) even if installed as dependency into vendor/ dir, so that it is not necessary to define the paths manually.
- Commands are now triggering Events (see CommandEvents), which could be used to extend commands with custom features.

# Changelog

<!-- There is always Unreleased section on the top. Subsections (Added, Changed, Fixed, Removed) should be added as needed. -->

## Unreleased
- Nothing yet - everything is released.

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
- Workaround for Firefox locking port collisions (consequence of Selenium issue [#5172](https://code.google.com/p/selenium/issues/detail?id=5172)) - if FF cannot be started, try it again up to five times

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

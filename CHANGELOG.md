# Changelog

<!-- There is always Unreleased section on the top. Subsections (Added, Changed, Fixed, Removed) should be added as needed. -->

## Unreleased
### Added
- Nothing.

### Removed
- Nothing.

### Changed
- Test-cases order is optimized before running tests, so that test-cases with the longest delay of theirs dependencies are run as first.

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
- The `install` command now checks for the latest version of Selenium server and interactively asks for confirmation
- Possibility to add custom test Publishers (enabled by `--publish-results`). Pass it as argument of TestStatusListener in `phpunit.xml`.
- During the `run-tests` execution, current test results are generated into `logs/results.xml`. This is useful eg. on long-lasting Jenkins jobs or if you want to know more precise status of the current (or last) test run.
- Shortcut to WebDriver in components - it's now possible to write `$this->wd` instead of `$this->tc->wd`
- Possibility to set `--fixtures-dir` in `run-tests` command. This is handy eg. when tests are run on remote terminal and fixtures are located on network directory.
- Possibility to set `--logs-dir` in `run-tests` command. Useful when Steward core is installed as dependency and is run from vendor/bin/.
- Possibility to easily overload any browser-specific capability, using custom (overloaded) WebdriverListener
- Specified group(s) could be excluded from the `run-tests`, using `--exclude-group` option

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

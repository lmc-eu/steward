# Changelog

## Unreleased

### Added
- CHANGELOG.md file.
- Global `DEBUG` constant (is true if -vvv passed to run-tests), that could be used in tests to print some more verbose output.
- The `install` command now checks for the latest version of Selenium server and interactively asks for confirmation
- Possibility to add custom test Publishers (enabled by `--publish-results`). Pass it as argument of TestStatusListener in `phpunit.xml`.
- During the `run-tests` execution, current test results are generated into `logs/results.xml`. This is useful eg. on long-lasting Jenkins jobs or if you want to know more precise status of the current (or last) test run.
- Shortcut to WebDriver in components - it's now possible to write `$this->wd` instead of `$this->tc->wd`
- Possibility to set `--fixtures-dir` in `run-tests` command. This is handy eg. when tests are run on remote terminal and fixtures are located on network directory.
- Possibility to set `--logs-dir` in `run-tests` command. Useful when Steward core is installed as dependency and is run from vendor/bin/. 

### Changed
- The last argument of run-tests command (browser name) is now always required (as well as the environment name)
- The --publish-results parameter is now used without a value (as a switch to enable publishing test results).
- Unified testcases and test status and results naming:
    - Testcases (= process) statuses: done, prepared, queued
    - Testcases (= process) results (for "done" status): passed, failed, fatal
    - Test statuses: started, done
    - Test results (for "done" status): passed, failed, broken, skipped, incomplete
- The `install` command now outputs only full path to jar file in `-no-interactive` mode (except `-vv` or `-vvv` is passed) and nothing else
- Path to tests in `run-tests` command is now defined using `--tests-dir`
- Browser resolution is by default 1280x1024 and could easily be set eg. in AbstractTestCase using `$browserWidth` and `$browserHeight` properties.

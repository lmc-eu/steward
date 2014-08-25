# Changelog

## Unreleased

### Added
- CHANGELOG.md file.
- Global DEBUG constant (is true if -vvv passed to run-tests), that could be used in tests to print some more verbose output.

### Changed
- The last argument of run-tests command (browser name) is now always required (as well as the environment name)
- The --publish-results parameter is now used without a value (as a switch to enable publishing test results).

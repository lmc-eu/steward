<?php declare(strict_types=1);

namespace Lmc\Steward\Test;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverDimension;
use Lmc\Steward\ConfigProvider;
use PHPUnit\Framework\TestCase;

/**
 * Abstract test case to be used by all test cases.
 * It adds logging, some common logic and assertions.
 */
abstract class AbstractTestCase extends TestCase
{
    use SyntaxSugarTrait;

    /** @var int|null Default width of browser window. Use null to disable setting default window size on startup. */
    public const BROWSER_WIDTH = 1280;
    /** @var int|null Default height of browser window. Use null to disable setting default window size on startup. */
    public const BROWSER_HEIGHT = 1024;

    /** @var RemoteWebDriver */
    public $wd;
    /** @var string Log appended to output of this test */
    protected $appendedTestLog = '';

    protected function setUp(): void
    {
        if ($this->wd instanceof RemoteWebDriver && static::BROWSER_WIDTH !== null && static::BROWSER_HEIGHT !== null) {
            $this->wd->manage()->window()->setSize(
                new WebDriverDimension(static::BROWSER_WIDTH, static::BROWSER_HEIGHT)
            );
        }
    }

    /**
     * Get output of current test. Parent method is overwritten to include also $appendedTestLog in the output
     * (called eg. from PHPUnit\Util\Log\JUnit).
     */
    public function getActualOutput(): string
    {
        $output = parent::getActualOutput();
        $output .= $this->appendedTestLog;

        return $output;
    }

    /**
     * Append given output at the end of test's log. This is useful especially when called from
     * Listeners, as the standard output won't be part of test output buffer
     *
     * @see log
     */
    public function appendTestLog(string $format, ...$args): void
    {
        $output = $this->formatOutput($format, $args);
        $this->appendedTestLog .= $output;
    }

    /**
     * Append already formatted log (including timestamp, newlines etc.) to end of test's log.
     *
     * @see appendTestLog
     */
    public function appendFormattedTestLog(string $formattedLog): void
    {
        $this->appendedTestLog .= $formattedLog;
    }

    /**
     * Log to output
     *
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed ...$args Variable number of parameters inserted into $format string
     */
    public function log(string $format, ...$args): void
    {
        echo $this->formatOutput($format, $args);
    }

    /**
     * Log warning to output. Unlike log(), it will be prefixed with "WARN: " and colored.
     *
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed ...$args Variable number of parameters inserted into $format string
     */
    public function warn(string $format, ...$args): void
    {
        echo $this->formatOutput($format, $args, 'WARN');
    }

    /**
     * Log to output, but only if debug mode is enabled.
     *
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param mixed ...$args Variable number of parameters inserted into $format string
     */
    public function debug(string $format, ...$args): void
    {
        if (ConfigProvider::getInstance()->debug) {
            echo $this->formatOutput($format, $args, 'DEBUG');
        }
    }

    /**
     * Sleep for given amount of seconds. Unlike sleep(), also the float values are supported.
     * ALWAYS TRY TO USE WAIT() AND EXPLICIT WAITS INSTEAD!
     *
     * @see https://github.com/php-webdriver/php-webdriver/wiki/HowTo-Wait
     */
    public static function sleep(float $seconds): void
    {
        $fullSecond = (int) floor($seconds);
        $microseconds = (int) (fmod($seconds, 1) * 1000000000);

        time_nanosleep($fullSecond, $microseconds);
    }

    /**
     * Format output
     *
     * @param string $format The format string. May use "%" placeholders, in a same way as sprintf()
     * @param array $args Array of arguments passed to original sprintf()-like function
     * @param string $type Specific log severity type (WARN, DEBUG) prefixed to output
     * @return string Formatted output
     */
    protected function formatOutput(string $format, array $args, string $type = ''): string
    {
        // If first item of arguments contains another array use it as arguments
        if (!empty($args) && is_array($args[0])) {
            $args = $args[0];
        }

        return '[' . date('Y-m-d H:i:s') . ']'
            . ($type ? " [$type]" : '') . ' '
            . vsprintf($format, $args)
            . "\n";
    }
}

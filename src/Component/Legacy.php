<?php
/**
 * Allows sharing data between test-cases and phases of tests
 *
 * Two possible uses
 * 1. loadWithName() and saveWithName() - you must define a name for the legacy which will be used as a filename
 * to store the data - beware - the name must be unique through all test-cases
 *
 * example:
 * class FooFirstTest
 * {
 *      public function test()
 *      {
 *          $legacy->saveWithName("some data to be remembered", 'my_test_case_legacy');
 *      }
 * }
 *
 * class FooSecondTest
 * {
 *      public function test()
 *      {
 *          $data = $legacy->loadWithName('my_test_case_legacy');
 *      }
 * }
 *
 *
 * 2. load() and save() - the name of the legacy (file) is generated from the name of the test case class and the name
 * of the test running - the class must have PhaseN in the name where N is a digit - this because different phases
 * of the test-case will differ in the digit but the rest of the name will be the same
 * - and so different phases of the same test-case can access the same legacy
 * You can choose whether the legacy should be shared between tests in a test case (class) or accessible only
 * by the same test function.
 *
 * example:
 * class FooPhase1Test
 * {
 *      public function test()
 *      {
 *          $legacy->save("some data to be remembered");
 *      }
 * }
 *
 * class FooPhase2Test
 * {
 *      public function test()
 *      {
 *          $data = $legacy->load();
 *      }
 * }
 */

namespace Lmc\Steward\Component;

use Lmc\Steward\ConfigProvider;
use Lmc\Steward\Test\AbstractTestCaseBase;
use Lmc\Steward\Utils\Strings;

/**
 * Legacy component allows you to share data between test-cases and phases of tests.
 */
class Legacy extends AbstractComponent
{
    const LEGACY_TYPE_CASE = 'CASE';
    const LEGACY_TYPE_TEST = 'TEST';

    /** @var string */
    protected $testClassName;

    /** @var string */
    protected $extension = '.legacy';

    /** @var string */
    protected $fileDir;

    public function __construct(AbstractTestCaseBase $tc)
    {
        parent::__construct($tc);

        $logsDir = ConfigProvider::getInstance()->logsDir;
        if ($logsDir) { // if the directory is not defined, the setFileDir() must be called explicitly later
            $this->setFileDir($logsDir);
        }

        $this->testClassName = get_class($tc);

        $this->log('New Legacy instantiated in class "%s"', $this->testClassName);
    }

    /**
     * Set directory where results file should be stored. Usable eg. when LOGS_DIR constant was not set.
     *
     * @param string $dir
     */
    public function setFileDir($dir)
    {
        $this->fileDir = $dir;
    }

    /**
     * Generates a filename (without path and extension) for the legacy based on the name of the test-case
     *
     * @param string $type LEGACY_TYPE_CASE (shared by all tests in test case)
     *      or LEGACY_TYPE_TEST (shared only by the same test function)
     * @throws LegacyException
     * @return string
     */
    protected function getLegacyName($type)
    {
        $name = $this->testClassName;

        if (!preg_match('/Phase\d/', $name)) {
            throw new LegacyException(
                "Cannot generate Legacy name from class without 'Phase' followed by number in name " . $name
            );
        }

        $name = preg_replace('/Phase\d/', '', $name); // remove 'PhaseX' from the name
        $name = Strings::toFilename($name);

        if ($type == self::LEGACY_TYPE_TEST) {
            $name .= '#' . Strings::toFilename($this->tc->getName(false));
        }

        return $name;
    }

    /**
     * Gets a path to file with legacy data
     *
     * @param string $filename
     * @return string
     */
    protected function getLegacyFullPath($filename)
    {
        return $this->fileDir . '/' . $filename . $this->extension;
    }

    /**
     * Store legacy of test under a custom name
     *
     * @param mixed $data
     * @param string $legacyName filename to store the data if null getLegacyFilename is called to generate filename
     *      from the test class name
     * @throws LegacyException
     */
    public function saveWithName($data, $legacyName)
    {
        $filename = $this->getLegacyFullPath($legacyName);
        $this->log('Saving data as Legacy "%s" to file "%s"', $legacyName, $filename);
        $this->debug('Legacy data: %s', $this->getPrintableValue($data));

        if (@file_put_contents($filename, serialize($data)) === false) {
            throw new LegacyException('Cannot save Legacy to file ' . $filename);
        }
    }

    /**
     * Store legacy of test getLegacyFilename is called to generate filename from the test class name
     *
     * @param mixed $data
     * @param string $type LEGACY_TYPE_CASE (shared by all tests in test case)
     *      or LEGACY_TYPE_TEST (shared only by the same test function)
     * @throws LegacyException
     */
    public function save($data, $type = self::LEGACY_TYPE_CASE)
    {
        $this->saveWithName($data, $this->getLegacyName($type));
    }

    /**
     * Reads legacy of test getLegacyFilename is called to generate filename from the test class name.
     * Raises exception if it is not found.
     *
     * @param string $type LEGACY_TYPE_CASE (shared by all tests in test case)
     *      or LEGACY_TYPE_TEST (shared only by the same test function)
     * @throws LegacyException
     * @return mixed
     */
    public function load($type = self::LEGACY_TYPE_CASE)
    {
        return $this->loadWithName($this->getLegacyName($type));
    }

    /**
     * Reads legacy specified by custom name.
     * Raises exception if it is not found.
     *
     * @param string $legacyName filename to store the data from the test class name
     * @throws LegacyException
     * @return mixed
     */
    public function loadWithName($legacyName)
    {
        $filename = $this->getLegacyFullPath($legacyName);

        $this->log('Reading Legacy "%s" from file "%s"', $legacyName, $filename);

        $data = @file_get_contents($filename);
        if ($data === false) {
            throw new LegacyException('Cannot read Legacy file ' . $filename);
        }

        $legacy = unserialize($data);
        if ($legacy === false) {
            throw new LegacyException('Cannot parse Legacy from file ' . $filename);
        }

        $this->debug('Legacy data: %s', $this->getPrintableValue($legacy));

        return $legacy;
    }

    /**
     * Converts legacy value to string that can be printed (e.g. in log)
     * calls __toString on the object if it's defined otherwise print_r()
     * @param mixed $obj
     * @return string
     */
    private function getPrintableValue($obj)
    {
        if (is_object($obj) && method_exists($obj, '__toString')) {
            return $obj;
        }

        return print_r($obj, true);
    }
}

<?php
/**
 * Allows sharing data between test-cases and phases of tests
 */

namespace Lmc\Steward\Test;

class Legacy
{
    /**
     * @var AbstractTestCaseBase
     */
    protected $test;

    /**
     * @var string
     */
    protected $testClassName;

    /**
     * Create Legacy instance
     * @param \Lmc\Steward\Test\AbstractTestCaseBase $test
     * @param string $testClassName
     */
    public function __construct(AbstractTestCaseBase $test, $testClassName)
    {
        $this->test = $test;
        $this->testClassName = $testClassName;
    }

    /**
     * Generates a filename (without path) for the legacy based on the name of the test-case
     * @return string
     * @throws LegacyException
     */
    protected function getLegacyName()
    {
        $name = $this->testClassName;

        if (preg_match('/Phase\d/', $name)) {
            $name = preg_replace('/Phase\d/', '', $name);
            $name = str_replace(['/', '\\'], '-', $name);
            $name .= '#' . $this->test->getName() . ".legacy";
        } else {
            throw new LegacyException(
                "Cannot generate legacy name from class without 'Phase' followed by number in name " . $name);
        }
        return $name;
    }

    /**
     * Makes a fully qualified path to file with legacy
     * @param $filename
     * @return string
     */
    protected function makeLegacyFullPath($filename)
    {
        return "logs/" . $filename;
    }

    /**
     * Store legacy of test under a custom name
     * @param $data
     * @param string $legacyName filename to store the data if null getLegacyFilename is called to generate filename
     *      from the test class name
     * @throws LegacyException
     */
    public function saveWithName($data, $legacyName)
    {
        $filename = $this->makeLegacyFullPath($legacyName);
        if (file_put_contents($filename, serialize($data)) === false) {
            throw new LegacyException("Cannot save legacy to file " . $filename);
        }
    }

    /**
     * Store legacy of test getLegacyFilename is called to generate filename
     *      from the test class name
     * @param $data
     * @throws LegacyException
     */
    public function save($data)
    {
        $this->saveWithName($data, $this->getLegacyName());
    }

    /**
     * Reads legacy of test getLegacyFilename is called to generate filename
     * from the test class name
     * raises exception if it is not found
     * @return Mixed
     * @throws LegacyException
     */
    public function load()
    {
        return $this->loadWithName($this->getLegacyName());
    }

    /**
     * Reads legacy specified by custom name
     * raises exception if it is not found
     * @param string $legacyName filename to store the data
     *      from the test class name
     * @return Mixed
     * @throws LegacyException
     */
    public function loadWithName($legacyName)
    {
        $filename = $this->makeLegacyFullPath($legacyName);

        // if the file doesn't exist - raise exception
        if (!file_exists($filename)) {
            throw new LegacyException("Cannot find legacy file " . $filename);
        }

        $data = file_get_contents($filename);
        if ($data===false) {
            throw new LegacyException("Cannot read legacy file " . $filename);
        }

        $legacy = unserialize($data);
        if ($legacy===false) {
            throw new LegacyException("Cannot parse legacy form file " . $filename);
        }

        return $legacy;
    }

}

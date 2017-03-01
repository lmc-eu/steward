<?php

namespace Lmc\Steward\Process;

use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Parse colon delimited key:value capabilities passed as an CLI option
 */
class KeyValueCapabilityOptionsParser
{
    const DELIMITER = ':';

    /**
     * @param array $capabilities
     * @return array
     */
    public function parse(array $capabilities)
    {
        $outputCapabilities = [];

        foreach ($capabilities as $capability) {
            $parts = explode(self::DELIMITER, $capability);
            if (!isset($parts[0], $parts[1])) {
                throw new RuntimeException(
                    sprintf(
                        'Capability must be given in format "capabilityName:value" but "%s" was given',
                        $capability
                    )
                );
            }

            $outputCapabilities[$parts[0]] = $this->castToGuessedDataType($parts[1]);
        }

        return $outputCapabilities;
    }

    /**
     * Guest most appropriate data type acceptable by JSON
     *
     * @param string $value
     * @return mixed
     */
    private function castToGuessedDataType($value)
    {
        $stringValueWithoutQuotes = $this->removeEncapsulatingQuotes($value);
        if ($stringValueWithoutQuotes !== null) {
            return $stringValueWithoutQuotes;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT, []);
        if ($intValue !== false) {
            return $intValue;
        }

        $floatValue = filter_var($value, FILTER_VALIDATE_FLOAT, []);
        if ($floatValue !== false) {
            return $floatValue;
        }

        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
        if ($boolValue !== null) {
            return $boolValue;
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function removeEncapsulatingQuotes($value)
    {
        $withoutDoubleQuotes = preg_replace('/^"(.+)"$/', '$1', $value);
        if ($withoutDoubleQuotes !== $value) {
            return $withoutDoubleQuotes;
        }

        $withoutSingleQuotes = preg_replace("/^'(.+)'$/", '$1', $value);
        if ($withoutSingleQuotes !== $value) {
            return $withoutSingleQuotes;
        }

        return null;
    }
}

<?php

namespace Lmc\Steward\Process;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * @covers Lmc\Steward\Process\KeyValueCapabilityOptionsParser
 */
class KeyValueCapabilityOptionsParserTest extends TestCase
{
    /**
     * @dataProvider provideCapabilities
     * @param array $capabilityOption
     * @param array $expectedCapabilities
     */
    public function testShouldPropagateCapabilities(array $capabilityOption, array $expectedCapabilities)
    {
        $parser = new KeyValueCapabilityOptionsParser();

        $parsedCapabilities = $parser->parse($capabilityOption);

        $this->assertSame($expectedCapabilities, $parsedCapabilities);
    }

    /**
     * @return array[]
     */
    public function provideCapabilities()
    {
        return [
            'string value' => [['stringValue:thisIsString'], ['stringValue' => 'thisIsString']],
            'value with special chars' => [
                ['webdriver.log.file:/foo/bar.log'],
                ['webdriver.log.file' => '/foo/bar.log'],
            ],
            'capability value with spaces' => [['platform:OS X 10.8'], ['platform' => 'OS X 10.8']],
            'double enquoted string value should have the quotes removed' => [
                ['stringValue:"thisIsString"'],
                ['stringValue' => 'thisIsString'],
            ],
            'single enquoted string value should have the quotes removed' => [
                ["stringValue:'thisIsString'"],
                ['stringValue' => 'thisIsString'],
            ],
            'double enquoted number should be cast to string' => [['version:"47"'], ['version' => '47']],
            'single enquoted number should be cast to string' => [["version:'47'"], ['version' => '47']],
            'double enquoted float number should be cast to string' => [
                ['version:"14.14393"'],
                ['version' => '14.14393'],
            ],
            'single enquoted float number should be cast to string' => [
                ["version:'14.14393'"],
                ['version' => '14.14393'],
            ],
            'double enquoted bool should be cast to string' => [['foo:"true"'], ['foo' => 'true']],
            'single enquoted bool should be cast to string' => [["foo:'true'"], ['foo' => 'true']],
            'wrongly quoted value should be cast to string but with quotations intact' => [
                ['foo:"bar\\\''],
                ['foo' => "\"bar\\'"],
            ],
            'integer value' => [['someNumber:1337'], ['someNumber' => 1337]],
            'zero number value' => [['zeroValue:0'], ['zeroValue' => 0]],
            'float value' => [['floatValue:1.337'], ['floatValue' => 1.337]],
            'boolean true value' => [['trueValue:true'], ['trueValue' => true]],
            'boolean false value' => [['falseValue:false'], ['falseValue' => false]],
            'multiple values' => [
                ['stringValue:thisIsString', 'trueValue:true'],
                ['stringValue' => 'thisIsString', 'trueValue' => true],
            ],
        ];
    }

    public function testShouldNotAcceptCapabilitiesInWrongFormat()
    {
        $parser = new KeyValueCapabilityOptionsParser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Capability must be given in format "capabilityName:value" but "foo" was given');
        $parser->parse(['foo']);
    }
}

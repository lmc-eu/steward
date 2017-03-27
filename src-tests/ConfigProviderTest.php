<?php

namespace Lmc\Steward;

use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    /** @var array */
    protected $environmentVariables = [];

    protected function setUp()
    {
        $this->environmentVariables = ConfigHelper::getDummyConfig();

        ConfigHelper::unsetConfigInstance();
    }

    public function testShouldRetrieveConfigurationValuesFromEnvironmentAndUseCamelCaseKeysForThem()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);
        $config = ConfigProvider::getInstance();

        $this->assertEquals('http://server.tld:4444', $config->serverUrl);

        $this->assertEquals('firefox', $config->browserName);
    }

    public function testShouldMakeConfigOptionsAccessibleDirectlyThroughConfigProvider()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);

        $this->assertEquals('http://server.tld:4444', ConfigProvider::getInstance()->serverUrl);
    }

    public function testShouldNotAllowToChangeTheValues()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);

        $config = ConfigProvider::getInstance();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Configuration values are immutable after initialization and cannot be changed');

        $config->serverUrl = 'foo';
    }

    public function testShouldNotAllowToUnsetValues()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);

        $config = ConfigProvider::getInstance();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Configuration values are immutable after initialization and cannot be changed');

        unset($config->serverUrl);
    }

    public function testShouldDetectEmptyConfigOption()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);
        $config = ConfigProvider::getInstance();

        $nonEmptyValue = $config->serverUrl;
        $emptyValue = $config->capability;

        $isNonEmptyValueEmpty = empty($nonEmptyValue);
        $this->assertFalse($isNonEmptyValueEmpty);
        $this->assertTrue(isset($nonEmptyValue));
        $this->assertTrue($config->__isset('serverUrl'));

        $isEmptyValueEmpty = empty($emptyValue);
        $this->assertTrue($isEmptyValueEmpty);
        $this->assertTrue(isset($emptyValue));
        $this->assertTrue($config->__isset('capability'));
    }

    public function testShouldThrowExceptionWhenAccessingNotExistingConfigOptionThroughConfigProvider()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Configuration option "notExisting" was not defined');

        ConfigProvider::getInstance()->notExisting;
    }

    public function testShouldOnlyHoldOneInstanceOfConfigObject()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);
        $firstInstance = ConfigProvider::getInstance();
        $this->assertInstanceOf(ConfigProvider::class, $firstInstance);

        $secondInstance = ConfigProvider::getInstance();
        $this->assertSame($firstInstance, $secondInstance);

        $thirdInstanceDeprecatedRetrieval = ConfigProvider::getInstance()->getConfig();
        $this->assertSame($firstInstance, $thirdInstanceDeprecatedRetrieval);
    }

    public function testShouldFailIfRequiredOptionIsNotDefined()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);
        putenv('SERVER_URL'); // unset value

        $config = ConfigProvider::getInstance();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SERVER_URL environment variable must be defined');

        $config->serverUrl;
    }

    public function testShouldAllowToAddCustomConfigurationOptions()
    {
        $config = ConfigProvider::getInstance();

        $config->setCustomConfigurationOptions(['CUSTOM_OPTION']);

        // Set environment values for custom option
        $this->environmentVariables['CUSTOM_OPTION'] = 'new';
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);

        $this->assertEquals('new', $config->customOption);
    }

    public function testShouldFailIfSettingCustomConfigurationOptionsAfterFirstInstantiation()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);
        $config = ConfigProvider::getInstance();

        // Trigger initialization
        $config->serverUrl;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Custom configuration options can be set only before initialization of configuration'
        );

        // This should fail, as the Config instance was already created
        $config->setCustomConfigurationOptions(['CUSTOM_OPTION']);
    }
}

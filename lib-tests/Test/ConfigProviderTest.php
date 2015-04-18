<?php

namespace Lmc\Steward\Test;

use Configula\Config;

class ConfigProviderTest extends \PHPUnit_Framework_TestCase
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
        $config = ConfigProvider::getInstance()->getConfig();

        // Property access
        $this->assertEquals('http://server.tld:port', $config->serverUrl);
        // getItem() access
        $this->assertEquals('http://server.tld:port', $config->getItem('serverUrl'));
        // all items retrieval
        $this->assertInternalType('array', $config->getItems());
    }

    public function testShouldMakeConfigOptionsAccessibleDirectlyThroughConfigProvider()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);

        $this->assertEquals('http://server.tld:port', ConfigProvider::getInstance()->serverUrl);
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Configuration option "notExisting" was not defined
     */
    public function testShouldThrowExceptionWhenAccessingNotExistingConfigOptionThroughConfigProvider()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);

        ConfigProvider::getInstance()->notExisting;
    }

    public function testShouldOnlyHoldOneInstanceOfConfigObject()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);
        $provider = ConfigProvider::getInstance();
        $firstInstance = $provider->getConfig();
        $this->assertInstanceOf(Config::class, $firstInstance);

        $secondInstance = ConfigProvider::getInstance()->getConfig();
        $this->assertSame($firstInstance, $secondInstance);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage SERVER_URL environment variable must be defined
     */
    public function testShouldFailIfRequiredOptionIsNotDefined()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);
        putenv('SERVER_URL'); // unset value

        $provider = ConfigProvider::getInstance();
        $provider->getConfig();
    }

    public function testShouldAllowToAddCustomConfigurationOptions()
    {
        $provider = ConfigProvider::getInstance();

        $provider->setCustomConfigurationOptions(['CUSTOM_OPTION']);

        // Set environment values for custom option
        $this->environmentVariables['CUSTOM_OPTION'] = 'new';
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);

        $config = $provider->getConfig();

        $this->assertEquals('new', $config->customOption);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Custom configuration options can be set only before the Config object was instantiated
     */
    public function testShouldFailIfSettingCustomConfigurationOptionsAfterFirstInstantiation()
    {
        ConfigHelper::setEnvironmentVariables($this->environmentVariables);
        $provider = ConfigProvider::getInstance();
        // Create Config instance
        $provider->getConfig();

        // This should fail, as the Config instance was already created
        $provider->setCustomConfigurationOptions(['CUSTOM_OPTION']);
    }
}

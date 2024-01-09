<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Configuration;

use Lmc\Steward\Console\Configuration\Fixtures\DoesNotImplementInterface;
use Lmc\Steward\Console\Configuration\Fixtures\ImplementsCapabilitiesResolverInterface;
use Lmc\Steward\Selenium\CustomCapabilitiesResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Lmc\Steward\Console\Configuration\OptionsResolverConfigurator
 */
class OptionsResolverConfiguratorTest extends TestCase
{
    public function testShouldConfigureGivenOptionsResolver(): void
    {
        $optionsResolver = new OptionsResolver();
        $configurator = new OptionsResolverConfigurator();

        $this->assertEmpty($optionsResolver->getDefinedOptions());

        $configurator->configure($optionsResolver);

        $this->assertNotEmpty($optionsResolver->getDefinedOptions());
    }

    public function testShouldNotAllowNotExistingClassAsCapabilitiesResolverValue(): void
    {
        $optionsResolver = new OptionsResolver();
        $configurator = new OptionsResolverConfigurator();
        $configurator->configure($optionsResolver);

        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(
            'The option "capabilities_resolver" is invalid - passed class "\NotExisting" does not exist'
        );
        $optionsResolver->resolve([ConfigOptions::CAPABILITIES_RESOLVER => '\NotExisting']);
    }

    public function testShouldNotAllowNotCapabilitiesResolverInterfaceClassAsCapabilitiesResolverValue(): void
    {
        $optionsResolver = new OptionsResolver();
        $configurator = new OptionsResolverConfigurator();
        $configurator->configure($optionsResolver);

        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'The option "capabilities_resolver" is invalid - passed class "%s" does not implement interface "%s',
                DoesNotImplementInterface::class,
                CustomCapabilitiesResolverInterface::class
            )
        );

        $optionsResolver->resolve([ConfigOptions::CAPABILITIES_RESOLVER => DoesNotImplementInterface::class]);
    }

    public function testShouldAllowCapabilitiesResolverInterfaceClassAsCapabilitiesResolverValue(): void
    {
        $optionsResolver = new OptionsResolver();
        $configurator = new OptionsResolverConfigurator();
        $configurator->configure($optionsResolver);

        $output = $optionsResolver->resolve(
            [ConfigOptions::CAPABILITIES_RESOLVER => ImplementsCapabilitiesResolverInterface::class]
        );

        $this->assertSame(
            ImplementsCapabilitiesResolverInterface::class,
            $output[ConfigOptions::CAPABILITIES_RESOLVER]
        );
    }
}

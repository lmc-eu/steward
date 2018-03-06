<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Configuration;

use Lmc\Steward\Selenium\CustomCapabilitiesResolverInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Configure given OptionsResolver instance to values accepted by Steward configuration file
 */
class OptionsResolverConfigurator
{
    public function configure(OptionsResolver $optionsResolver): OptionsResolver
    {
        $this->configureCapabilitiesResolverOption($optionsResolver);
        $this->configureDirsOption($optionsResolver);

        return $optionsResolver;
    }

    private function configureCapabilitiesResolverOption(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefault(ConfigOptions::CAPABILITIES_RESOLVER, '');

        $optionsResolver->setAllowedValues(ConfigOptions::CAPABILITIES_RESOLVER, function ($value): bool {
            if (empty($value)) {
                return true;
            }

            // Note we throw an exception instead of returning false - to have more understandable exception message
            if (!class_exists($value)) {
                throw new InvalidOptionsException(
                    sprintf(
                        'The option "%s" is invalid - passed class "%s" does not exist',
                        'capabilities_resolver',
                        $value
                    )
                );
            }

            if (!is_subclass_of($value, CustomCapabilitiesResolverInterface::class)) {
                throw new InvalidOptionsException(
                    sprintf(
                        'The option "%s" is invalid - passed class "%s" does not implement interface "%s"',
                        'capabilities_resolver',
                        $value,
                        CustomCapabilitiesResolverInterface::class
                    )
                );
            }

            return true;
        });
    }

    private function configureDirsOption(OptionsResolver $optionsResolver): void
    {
        $dirs = [
            ConfigOptions::TESTS_DIR,
            ConfigOptions::LOGS_DIR,
        ];

        foreach ($dirs as $dirOption) {
            $optionsResolver->setDefined($dirOption);
            $optionsResolver->setAllowedTypes($dirOption, 'string');
        }
    }
}

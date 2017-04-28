<?php

namespace Lmc\Steward\Console\Configuration;

use Lmc\Steward\Selenium\CustomCapabilitiesResolverInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Configure given OptionsResolver instance to values accepted by Steward configuration file
 */
class OptionsResolverConfigurator
{
    /**
     * @param OptionsResolver $optionsResolver
     * @return OptionsResolver
     */
    public function configure(OptionsResolver $optionsResolver)
    {
        $this->configureCapabilitiesResolverOption($optionsResolver);
        $this->configureDirsOption($optionsResolver);

        return $optionsResolver;
    }

    private function configureCapabilitiesResolverOption(OptionsResolver $optionsResolver)
    {
        $optionsResolver->setDefault(ConfigOptions::CAPABILITIES_RESOLVER, '');

        $optionsResolver->setAllowedValues(ConfigOptions::CAPABILITIES_RESOLVER, function ($value) {
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

    private function configureDirsOption(OptionsResolver $optionsResolver)
    {
        $dirs = [
            ConfigOptions::TESTS_DIR,
            ConfigOptions::LOGS_DIR,
            ConfigOptions::FIXTURES_DIR,
        ];

        foreach ($dirs as $dirOption) {
            $optionsResolver->setDefined($dirOption);
            $optionsResolver->setAllowedTypes($dirOption, 'string');
        }
    }
}

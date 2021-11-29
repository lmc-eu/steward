<?php declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\PHP\ForbiddenFunctionsSniff;
use PhpCsFixer\Fixer\FunctionNotation\FunctionTypehintSpaceFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitExpectationFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set(
        Option::SKIP,
        [
            ForbiddenFunctionsSniff::class => [
                'src-tests/bootstrap.php',
                'src-tests/ConfigHelper.php',
                'src-tests/ConfigProviderTest.php',
                'src/bootstrap.php',
                'src/Component/Legacy.php',
                'src/Listener/TestStartLogListener.php',
                'src/Listener/TestStatusListener.php',
                'src/Test/AbstractTestCase.php',
                'src/WebDriver/RemoteWebDriver.php',
            ],
            FunctionTypehintSpaceFixer::class => [ // broken for '&' when args in anonymous function passed by reference
                'src-tests/Selenium/SeleniumServerAdapterTest.php',
            ],
            'PHP_CodeSniffer\Standards\Generic\Sniffs\Files\OneClassPerFileSniff.MultipleFound' => [
                'src-tests/Process/Fixtures/InvalidTests/MultipleClassesInFileTest.php',
            ],
            'PHP_CodeSniffer\Standards\Squiz\Sniffs\Arrays\ArrayDeclarationSniff.NoKeySpecified' => [
                'ecs.php',
            ],
            'src-tests/coverage/*',
            'src-tests/FunctionalTests/logs/coverage/*',
            'src-tests/Utils/Annotations/Fixtures/*',
        ]
    );

    $containerConfigurator->import(__DIR__ . '/vendor/lmc/coding-standard/ecs.php');

    $services = $containerConfigurator->services();

    $services->set(LineLengthFixer::class)
        ->call(
            'configure',
            [['line_length' => 120, 'break_long_lines' => true, 'inline_short_lines' => false]]
        );

    if (PHP_VERSION_ID >= 80000) { // The check fails on PHP <8.0. See https://github.com/symplify/symplify/issues/3130
        $services->set(PhpUnitExpectationFixer::class)
            ->call('configure', [['target' => '8.4']]);
    }
};

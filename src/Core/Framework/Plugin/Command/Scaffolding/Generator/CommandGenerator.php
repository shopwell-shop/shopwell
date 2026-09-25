<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('framework')]
class CommandGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-command';
    private const OPTION_DESCRIPTION = 'Create an example console command';
    private const CLI_QUESTION = 'Do you want to create an example console command?';

    private string $servicesPhpEntry = <<<'EOL'

    $services->set(\{{ namespace }}\Command\ExampleCommand::class)
        ->tag('console.command');

EOL;

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createCommand($configuration));

        $stubCollection->append(
            'src/Resources/config/services.php',
            str_replace(
                '{{ namespace }}',
                $configuration->namespace,
                $this->servicesPhpEntry
            )
        );
    }

    private function createCommand(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Command/ExampleCommand.php',
            self::STUB_DIRECTORY . '/command.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }
}

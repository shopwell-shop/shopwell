<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Demodata\Command\DemodataCommand;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class DemodataCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $demodataCommand = $container->getDefinition(DemodataCommand::class);

        foreach ($container->findTaggedServiceIds('shopwell.demodata_generator') as $tags) {
            foreach ($tags as $tag) {
                $name = $tag['option-name'] ?? null;
                if ($name === null) {
                    continue;
                }

                $default = $tag['option-default'] ?? 0;
                $description = $tag['option-description'] ?? \ucfirst((string) $name) . ' count';

                $demodataCommand->addMethodCall('addDefault', [
                    $name,
                    $default,
                ]);

                $demodataCommand->addMethodCall('addOption', [
                    $name,
                    null,
                    InputOption::VALUE_OPTIONAL,
                    $description,
                ]);
            }
        }
    }
}

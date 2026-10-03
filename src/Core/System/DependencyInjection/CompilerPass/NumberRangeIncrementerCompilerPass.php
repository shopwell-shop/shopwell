<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class NumberRangeIncrementerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('shopwell.number_range.config.connection') !== null) {
            return;
        }

        // we remove service from container when required configurations are missing
        // we always keep mysql storage so MigrateIncrementStorageCommand works
        $container->removeDefinition('shopwell.number_range.redis');
        $container->removeDefinition(IncrementRedisStorage::class);
    }
}

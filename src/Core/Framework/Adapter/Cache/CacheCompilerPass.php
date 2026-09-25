<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Cache;

use Shopwell\Core\Framework\Adapter\AdapterException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class CacheCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $storage = $container->getParameter('shopwell.cache.invalidation.delay_options.storage');

        switch ($storage) {
            case 'mysql':
                $container->removeDefinition('shopwell.cache.invalidator.storage.redis_adapter');
                $container->removeDefinition('shopwell.cache.invalidator.storage.redis');
                break;
            case 'redis':
                if ($container->getParameter('shopwell.cache.invalidation.delay_options.connection') === null) {
                    throw AdapterException::missingRequiredParameter('shopwell.cache.invalidation.delay_options.connection');
                }

                $container->removeDefinition('shopwell.cache.invalidator.storage.mysql');
                break;
        }
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection\CompilerPass;

use Shopwell\Core\Checkout\Cart\CartPersister;
use Shopwell\Core\Checkout\Cart\RedisCartPersister;
use Shopwell\Core\Checkout\DependencyInjection\DependencyInjectionException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('checkout')]
class CartStorageCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $storage = $container->getParameter('shopwell.cart.storage.type');

        switch ($storage) {
            case 'mysql':
                $container->removeDefinition('shopwell.cart.redis');
                $container->removeDefinition(RedisCartPersister::class);
                break;
            case 'redis':
                if ($container->getParameter('shopwell.cart.storage.config.connection') === null) {
                    throw DependencyInjectionException::redisNotConfiguredForCartStorage();
                }

                $container->removeDefinition(CartPersister::class);
                $container->setAlias(CartPersister::class, RedisCartPersister::class);
                break;
        }
    }
}

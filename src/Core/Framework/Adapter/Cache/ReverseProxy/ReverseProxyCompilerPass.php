<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Cache\ReverseProxy;

use Shopwell\Core\Framework\Adapter\Cache\Http\CacheStore;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class ReverseProxyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('shopwell.http_cache.reverse_proxy.enabled')) {
            $container->removeDefinition(ReverseProxyCache::class);
            $container->removeDefinition(AbstractReverseProxyGateway::class);
            $container->removeDefinition(FastlyReverseProxyGateway::class);
            $container->removeDefinition(FastlyReverseProxyGateway::class);

            return;
        }

        $container->removeDefinition(CacheStore::class);

        $container->setAlias(CacheStore::class, ReverseProxyCache::class);
        $container->getAlias(CacheStore::class)->setPublic(true);

        if ($container->getParameter('shopwell.http_cache.reverse_proxy.fastly.enabled')) {
            $container->setAlias(AbstractReverseProxyGateway::class, FastlyReverseProxyGateway::class);
        }
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class FilesystemConfigMigrationCompilerPass implements CompilerPassInterface
{
    private const MIGRATED_FS = ['theme', 'asset', 'sitemap'];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::MIGRATED_FS as $fs) {
            $key = \sprintf('shopwell.filesystem.%s', $fs);
            $urlKey = $key . '.url';
            $typeKey = $key . '.type';
            $configKey = $key . '.config';
            $visibilityKey = $key . '.visibility';

            if (!$container->hasParameter($visibilityKey)) {
                $container->setParameter($visibilityKey, '%shopwell.filesystem.public.visibility%');
            }

            if ($container->hasParameter($typeKey)) {
                continue;
            }

            // 6.1 always refers to the main shop url on theme, asset and sitemap.
            $container->setParameter($urlKey, '');
            $container->setParameter($key, '%shopwell.filesystem.public%');
            $container->setParameter($typeKey, '%shopwell.filesystem.public.type%');
            $container->setParameter($configKey, '%shopwell.filesystem.public.config%');
        }

        if (!$container->hasParameter('shopwell.filesystem.public.url')) {
            $container->setParameter('shopwell.filesystem.public.url', '%shopwell.cdn.url%');
        }

        if (!$container->hasParameter('shopwell.filesystem.public.visibility')) {
            $container->setParameter('shopwell.filesystem.public.visibility', 'public');
        }
    }
}

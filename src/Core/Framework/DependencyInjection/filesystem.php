<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use League\Flysystem\FilesystemOperator;
use Shopwell\Core\Framework\Adapter\Asset\AssetInstallCommand;
use Shopwell\Core\Framework\Adapter\Asset\AssetService;
use Shopwell\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Shopwell\Core\Framework\Adapter\Asset\FlysystemLastModifiedVersionStrategy;
use Shopwell\Core\Framework\Adapter\Filesystem\Adapter\AwsS3v3Factory;
use Shopwell\Core\Framework\Adapter\Filesystem\Adapter\GoogleStorageFactory;
use Shopwell\Core\Framework\Adapter\Filesystem\Adapter\LocalFactory;
use Shopwell\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Shopwell\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopwell\Core\Framework\App\ActiveAppsLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Filesystem
    $services->set(FilesystemFactory::class)
        ->args([
            tagged_iterator('shopwell.filesystem.factory'),
        ]);

    $services->set('shopwell.filesystem.public', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('shopwell.filesystem.public'),
        ]);

    $services->set('shopwell.filesystem.private', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'privateFactory'])
        ->args([
            param('shopwell.filesystem.private'),
        ]);

    $services->set('shopwell.filesystem.temp', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'privateFactory'])
        ->args([
            param('shopwell.filesystem.temp'),
        ]);

    $services->set('shopwell.filesystem.theme', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('shopwell.filesystem.theme'),
        ]);

    $services->set('shopwell.filesystem.sitemap', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('shopwell.filesystem.sitemap'),
        ]);

    $services->set('shopwell.filesystem.asset', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('shopwell.filesystem.asset'),
        ]);

    $services->set(FilesystemFactory::class . '.local', LocalFactory::class)
        ->tag('shopwell.filesystem.factory');

    $services->set(FilesystemFactory::class . '.amazon_s3', AwsS3v3Factory::class)
        ->args([
            param('shopwell.filesystem.batch_write_size'),
            service('shopwell.filesystem.s3.client')->nullOnInvalid(),
        ])
        ->tag('shopwell.filesystem.factory');

    $services->set(FilesystemFactory::class . '.google_storage', GoogleStorageFactory::class)
        ->tag('shopwell.filesystem.factory');

    $services->set('console.command.assets_install', AssetInstallCommand::class)
        ->args([
            service('kernel'),
            service(AssetService::class),
            service(ActiveAppsLoader::class),
        ])
        ->tag('console.command');

    // Assets
    $services->set('shopwell.asset.public', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('shopwell.filesystem.public.url'),
            ],
            service('assets.empty_version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ])
        ->tag('shopwell.asset', ['asset' => 'public']);

    $services->set('shopwell.asset.public.version_strategy', FlysystemLastModifiedVersionStrategy::class)
        ->args([
            'theme-metaData',
            service('shopwell.filesystem.public'),
            service('cache.object'),
        ]);

    $services->set('shopwell.asset.theme.version_strategy', FlysystemLastModifiedVersionStrategy::class)
        ->args([
            'theme-metaData',
            service('shopwell.filesystem.theme'),
            service('cache.object'),
        ]);

    $services->set('shopwell.asset.asset.version_strategy', FlysystemLastModifiedVersionStrategy::class)
        ->args([
            'asset-metaData',
            service('shopwell.filesystem.asset'),
            service('cache.object'),
        ]);

    $services->set('shopwell.asset.asset', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('shopwell.filesystem.asset.url'),
            ],
            service('shopwell.asset.asset.version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ])
        ->tag('shopwell.asset', ['asset' => 'asset']);

    $services->set('shopwell.asset.asset_without_versioning', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('shopwell.filesystem.asset.url'),
            ],
            service('assets.empty_version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ]);

    $services->set('shopwell.asset.sitemap', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('shopwell.filesystem.sitemap.url'),
            ],
            service('assets.empty_version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ])
        ->tag('shopwell.asset', ['asset' => 'sitemap']);

    $services->set(CopyBatchInputFactory::class);
};

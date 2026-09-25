<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopwell\Core\Content\Media\File\FileNameProvider;
use Shopwell\Core\Content\Media\File\FileSaver;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Shopwell\Storefront\Theme\ThemeCollection;
use Shopwell\Storefront\Theme\ThemeEntity;
use Shopwell\Storefront\Theme\ThemeFilesystemResolver;
use Shopwell\Storefront\Theme\ThemeLifecycleService;
use Shopwell\Storefront\Theme\ThemeRuntimeConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeLifecycleService::class)]
class ThemeLifecycleServiceTest extends TestCase
{
    public function testRefreshThemeUpdatesExistingThemeAndRefreshesRuntimeConfig(): void
    {
        $context = Context::createDefaultContext();
        $configuration = new StorefrontPluginConfiguration('ExampleTheme');
        $configuration->setName('Example');
        $configuration->setAuthor('Author');
        $configuration->setThemeJson(null);
        $configuration->setThemeConfig(null);

        $theme = new ThemeEntity();
        $theme->setId('theme-id');
        $theme->setUniqueIdentifier('theme-id');
        $theme->setTechnicalName('ExampleTheme');
        $theme->setThemeJson(null);

        $themeRepository = StaticEntityRepository::of(ThemeCollection::class, [new ThemeCollection([$theme])]);
        /** @var StaticEntityRepository<EntityCollection<Entity>> $themeChildRepository */
        $themeChildRepository = new StaticEntityRepository([[]]);

        $runtimeConfig = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfig->expects($this->once())->method('refreshRuntimeConfig')->with(
            'theme-id',
            $configuration,
            $context,
            false,
            static::isInstanceOf(StorefrontPluginConfigurationCollection::class)
        );
        $runtimeConfig->expects($this->once())->method('resetCaches');

        $service = $this->createService($themeRepository, $themeChildRepository, $runtimeConfig);
        $service->refreshTheme($configuration, $context);

        static::assertCount(2, $themeRepository->upserts);
        static::assertSame($themeRepository->upserts[0], $themeRepository->upserts[1]);
        static::assertSame([[]], $themeChildRepository->deletes);
    }

    /**
     * @param EntityRepository<ThemeCollection> $themeRepository
     * @param EntityRepository<EntityCollection<Entity>> $themeChildRepository
     */
    private function createService(
        EntityRepository $themeRepository,
        EntityRepository $themeChildRepository,
        ThemeRuntimeConfigService $runtimeConfig,
    ): ThemeLifecycleService {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier('language-id');
        $locale = new LocaleEntity();
        $locale->setCode('en-GB');
        $language->setTranslationCode($locale);

        $registry = static::createStub(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        return new ThemeLifecycleService(
            $registry,
            $themeRepository,
            new StaticEntityRepository([]),
            StaticEntityRepository::of(MediaFolderCollection::class, [[]]),
            new StaticEntityRepository([]),
            static::createStub(FileSaver::class),
            static::createStub(FileNameProvider::class),
            static::createStub(ThemeFilesystemResolver::class),
            new StaticEntityRepository([new LanguageCollection([$language])]),
            $themeChildRepository,
            $connection,
            static::createStub(AbstractStorefrontPluginConfigurationFactory::class),
            $runtimeConfig,
        );
    }
}

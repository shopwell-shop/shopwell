<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopwell\Core\Content\Media\File\FileNameProvider;
use Shopwell\Core\Content\Media\File\FileSaver;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\App\Source\SourceResolver;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Kernel;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\Locale\LocaleCollection;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Storefront\Theme\Aggregate\ThemeTranslationCollection;
use Shopwell\Storefront\Theme\Aggregate\ThemeTranslationEntity;
use Shopwell\Storefront\Theme\Snippet\ThemeSnippetFileWriter;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Shopwell\Storefront\Theme\ThemeCollection;
use Shopwell\Storefront\Theme\ThemeEntity;
use Shopwell\Storefront\Theme\ThemeFilesystemResolver;
use Shopwell\Storefront\Theme\ThemeLifecycleService;
use Shopwell\Storefront\Theme\ThemeRuntimeConfigService;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\SimpleTheme\SimpleTheme;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\ThemeWithFileAssociations\ThemeWithFileAssociations;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\ThemeWithLabels\ThemeWithLabels;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeLifecycleServiceTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private ThemeLifecycleService $themeLifecycleService;

    private Context $context;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $mediaFolderRepository;

    private Connection $connection;

    private ThemeFilesystemResolver $themeFilesystemResolver;

    private ThemeRuntimeConfigService&Stub $themeRuntimeConfigService;

    protected function setUp(): void
    {
        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'ThemeWithFileAssociations' => new ThemeWithFileAssociations(),
            'ThemeWithLabels' => new ThemeWithLabels(),
            'SimpleTheme' => new SimpleTheme(),
        ]);

        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithFileAssociations', new ThemeWithFileAssociations()],
            ['ThemeWithLabels', new ThemeWithLabels()],
            ['SimpleTheme', new SimpleTheme()],
        ]);

        $this->themeFilesystemResolver = new ThemeFilesystemResolver(
            static::getContainer()->get(SourceResolver::class),
            $kernel
        );
        $this->themeRepository = static::getContainer()->get('theme.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->mediaFolderRepository = static::getContainer()->get('media_folder.repository');
        $this->connection = static::getContainer()->get(Connection::class);

        // tests that assert on the runtime config service build their own instance with a mock instead
        $this->themeRuntimeConfigService = static::createStub(ThemeRuntimeConfigService::class);
        $this->themeLifecycleService = $this->createThemeLifecycleService($this->themeRuntimeConfigService);

        $this->context = Context::createDefaultContext();
    }

    public function testRefreshThemesCorrectConfigurationCollection(): void
    {
        $pluginRegistry = static::getContainer()->get(StorefrontPluginRegistry::class);
        $pluginConfigurationCollection = $pluginRegistry->getConfigurations();
        $bundle = $this->getThemeConfig();
        $themeConfigurations = new StorefrontPluginConfigurationCollection([$bundle]);

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        foreach ($themeConfigurations as $themeConfiguration) {
            $runtimeConfigService->expects($this->once())
                ->method('refreshRuntimeConfig')
                ->with(static::anything(), $themeConfiguration, $this->context, false, $pluginConfigurationCollection);
        }

        $this->createThemeLifecycleService($runtimeConfigService)->refreshThemes($this->context, $themeConfigurations);
    }

    public function testItRegistersANewThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertCount(2, $themeEntity->getMedia());

        $themeDefaultFolderId = $this->getThemeMediaDefaultFolderId();
        foreach ($themeEntity->getMedia() as $media) {
            static::assertSame($themeDefaultFolderId, $media->getMediaFolderId());
        }
    }

    public function testThemeConfigInheritanceAddsParentTheme(): void
    {
        $parentBundle = $this->getThemeConfigWithLabels();
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);
        $bundle = $this->getThemeConfig();
        $bundle->setConfigInheritance(['@' . $parentBundle->getTechnicalName()]);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $parentThemeEntity = $this->getTheme($parentBundle);
        $themeEntity = $this->getTheme($bundle);

        static::assertSame($parentThemeEntity->getId(), $themeEntity->getParentThemeId());
    }

    public function testThemeConfigInheritanceUsesNearestThemeAsParent(): void
    {
        $grandParentBundle = $this->getThemeConfigWithLabels();
        $this->themeLifecycleService->refreshTheme($grandParentBundle, $this->context);

        $parentBundle = $this->getSimpleThemeConfig();
        $parentBundle->setConfigInheritance(['@Storefront', '@' . $grandParentBundle->getTechnicalName()]);
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);

        $bundle = $this->getThemeConfig();
        $bundle->setConfigInheritance([
            '@Storefront',
            '@' . $grandParentBundle->getTechnicalName(),
            '@' . $parentBundle->getTechnicalName(),
        ]);
        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        static::assertSame(
            $this->getTheme($parentBundle)->getId(),
            $this->getTheme($bundle)->getParentThemeId()
        );
    }

    public function testThemeRefreshWithParentTheme(): void
    {
        $parentBundle = $this->getThemeConfigWithLabels();
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);
        $bundle = $this->getThemeConfig();
        $bundle->setConfigInheritance(['@' . $parentBundle->getTechnicalName()]);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $parentThemeEntity = $this->getTheme($parentBundle);
        $themeEntity = $this->getTheme($bundle);

        static::assertSame($parentThemeEntity->getId(), $themeEntity->getParentThemeId());

        $bundle->setConfigInheritance([]);
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertSame($parentThemeEntity->getId(), $themeEntity->getParentThemeId());
    }

    public function testYouCanUpdateConfigToAddNewMedia(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);
        $this->addPinkLogoToTheme($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertCount(3, $themeEntity->getMedia());
    }

    public function testItWontThrowIfMediaHasRestrictDeleteAssociation(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $shopwellLogo = $this->getMedia('shopwell_logo');
        $this->createCmsPage($shopwellLogo->getId());

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        // assert that the file shopwell_logo was not deleted and is assigned to same media entity as before
        static::assertEquals($shopwellLogo, $this->getMedia('shopwell_logo'));
    }

    public function testItDontRenamesThemeMediaIfItExistsBeforeAndIsSame(): void
    {
        $bundle = $this->getThemeConfig();
        $this->addPinkLogoToTheme($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $shopwellLogoId = $this->getMedia('shopwell_logo');
        $this->createCmsPage($shopwellLogoId->getId());

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $renamedShopwellLogoId = $this->getMedia('shopwell_logo');
        static::assertNotNull($themeEntity->getMedia()->get($renamedShopwellLogoId->getId()));
    }

    public function testItRenamesThemeMediaIfItExistsBefore(): void
    {
        $bundle = $this->getThemeConfig();
        $this->addPinkLogoToThemeChanged($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $shopwellLogoId = $this->getMedia('shopwell_logo');
        $this->createCmsPage($shopwellLogoId->getId());

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $renamedShopwellLogoId = $this->getMedia('shopwell_logo_pink2');
        static::assertNotNull($themeEntity->getMedia()->get($renamedShopwellLogoId->getId()));
    }

    public function testItIgnoresMediaFieldsWithoutValue(): void
    {
        $bundle = $this->getThemeConfig();
        $this->addPinkLogoToThemeWithoutValue($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $shopwellLogoId = $this->getMedia('shopwell_logo');
        $this->createCmsPage($shopwellLogoId->getId());

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $this->hasNoMedia('shopwell_logo_pink2');
    }

    public function testItUploadsFilesIntoTheRootFolderIfThemeDefaultFolderDoesNotExist(): void
    {
        $bundle = $this->getThemeConfig();
        $themeMediaDefaultFolderId = $this->getThemeMediaDefaultFolderId();

        $this->connection->executeStatement('
            UPDATE `media`
            SET `media_folder_id` = null
            WHERE `media_folder_id` = :defaultThemeFolder
        ', ['defaultThemeFolder' => Uuid::fromHexToBytes($themeMediaDefaultFolderId)]);
        $this->mediaFolderRepository->delete([['id' => $themeMediaDefaultFolderId]], $this->context);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertCount(2, $themeEntity->getMedia());

        foreach ($themeEntity->getMedia() as $media) {
            static::assertNull($media->getMediaFolderId());
        }
    }

    public function testItDoesNotOverridePreviewIfSetExclusive(): void
    {
        $previewMediaId = Uuid::randomHex();
        $this->mediaRepository->create([
            [
                'id' => $previewMediaId,
            ],
        ], $this->context);

        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);
        $this->themeRepository->update([
            [
                'id' => $theme->getId(),
                'previewMediaId' => $previewMediaId,
            ],
        ], $this->context);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);
        static::assertSame($previewMediaId, $theme->getPreviewMediaId());
    }

    public function testItSkipsTranslationsIfLanguageIsNotAvailable(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $bundle = $this->getThemeConfigWithLabels();
        $this->deleteLanguageForLocale('zh-CN');

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);

        static::assertInstanceOf(ThemeTranslationCollection::class, $theme->getTranslations());
        static::assertCount(1, $theme->getTranslations());
        $firstTranslation = $theme->getTranslations()->first();
        static::assertNotNull($firstTranslation);
        static::assertSame('en-GB', $firstTranslation->getLanguage()?->getLocale()?->getCode());
        static::assertSame(['fields.sw-image' => 'test label'], Feature::silent('v6.8.0.0', fn () => $firstTranslation->getLabels()));
        static::assertSame(['fields.sw-image' => 'test help'], Feature::silent('v6.8.0.0', fn () => $firstTranslation->getHelpTexts()));
    }

    public function testItUsesEnglishTranslationsAsFallbackIfDefaultLanguageIsNotProvided(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $bundle = $this->getThemeConfigWithLabels();
        $this->changeDefaultLanguageLocale('zh-CN-1');

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);

        static::assertInstanceOf(ThemeTranslationCollection::class, $theme->getTranslations());
        static::assertCount(2, $theme->getTranslations());
        $translation = $this->getTranslationByLocale('zh-CN-1', $theme->getTranslations());
        static::assertSame([
            'fields.sw-image' => 'test label',
        ], Feature::silent('v6.8.0.0', fn () => $translation->getLabels()));
        static::assertSame([
            'fields.sw-image' => 'test help',
        ], Feature::silent('v6.8.0.0', fn () => $translation->getHelpTexts()));

        $chineseTranslation = $this->getTranslationByLocale('zh-CN', $theme->getTranslations());
        static::assertSame([
            'fields.sw-image' => '测试标签',
        ], Feature::silent('v6.8.0.0', fn () => $chineseTranslation->getLabels()));
        static::assertSame([
            'fields.sw-image' => '测试帮助',
        ], Feature::silent('v6.8.0.0', fn () => $chineseTranslation->getHelpTexts()));
    }

    public function testItRemovesAThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $themeMedia = $themeEntity->getMedia();
        $ids = $themeMedia->getIds();

        static::assertTrue($themeEntity->isActive());
        static::assertCount(2, $themeMedia);

        $themeDefaultFolderId = $this->getThemeMediaDefaultFolderId();
        foreach ($themeMedia as $media) {
            static::assertSame($themeDefaultFolderId, $media->getMediaFolderId());
        }

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService->expects($this->once())
            ->method('deleteByTechnicalName')
            ->with($bundle->getTechnicalName());

        $this->createThemeLifecycleService($runtimeConfigService)->removeTheme($bundle->getTechnicalName(), $this->context);

        // check whether the theme is no longer in the table and the associated media have been deleted
        static::assertFalse($this->hasTheme($bundle));
        static::assertCount(0, $this->mediaRepository->searchIds(new Criteria($ids), Context::createDefaultContext())->getIds());
    }

    public function testItRemovesAChildThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle, true);
        $childId = Uuid::randomHex();

        static::assertInstanceOf(ThemeCollection::class, $themeEntity->getDependentThemes());
        // check if we have no dependent Themes
        static::assertCount(0, $themeEntity->getDependentThemes());

        // clone theme and make it child
        $this->themeRepository->clone($themeEntity->getId(), $this->context, $childId, new CloneBehavior([
            'technicalName' => null,
            'name' => 'Cloned theme',
            'parentThemeId' => $themeEntity->getId(),
        ]));

        // refresh theme to get child
        $themeEntity = $this->getTheme($bundle, true);

        $themeMedia = $themeEntity->getMedia();
        static::assertInstanceOf(MediaCollection::class, $themeMedia);
        $ids = $themeMedia->getIds();

        static::assertTrue($themeEntity->isActive());
        static::assertCount(2, $themeMedia);
        static::assertInstanceOf(ThemeCollection::class, $themeEntity->getDependentThemes());
        static::assertCount(1, $themeEntity->getDependentThemes());

        $themeDefaultFolderId = $this->getThemeMediaDefaultFolderId();
        foreach ($themeMedia as $media) {
            static::assertSame($themeDefaultFolderId, $media->getMediaFolderId());
        }

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService->expects($this->once())
            ->method('deleteByTechnicalName')
            ->with($bundle->getTechnicalName());

        $this->createThemeLifecycleService($runtimeConfigService)->removeTheme($bundle->getTechnicalName(), $this->context);

        // check whether the theme is no longer in the table and the associated media have been deleted
        static::assertFalse($this->hasTheme($bundle));
        static::assertCount(0, $this->mediaRepository->searchIds(new Criteria($ids), Context::createDefaultContext())->getIds());
        static::assertCount(0, $this->themeRepository->search(new Criteria([$childId, $themeEntity->getId()]), $this->context)->getEntities());
    }

    public function testItGeneratesAdministrationSnippetsFromLegacyLabelsAndRemovesThemWithTheTheme(): void
    {
        $bundle = $this->getThemeConfigWithLabels();
        $privateFilesystem = static::getContainer()->get('shopwell.filesystem.private');
        $directory = 'snippets/administration/' . $bundle->getTechnicalName();

        try {
            $this->themeLifecycleService->refreshTheme($bundle, $this->context);

            static::assertTrue($privateFilesystem->fileExists($directory . '/en-GB.json'));
            static::assertTrue($privateFilesystem->fileExists($directory . '/zh-CN.json'));

            $snippets = \json_decode($privateFilesystem->read($directory . '/en-GB.json'), true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame(
                'test label',
                $snippets['sw-theme'][$bundle->getTechnicalName()]['default']['default']['default']['sw-image']['label'] ?? null,
            );

            $this->themeLifecycleService->removeTheme($bundle->getTechnicalName(), $this->context);

            static::assertFalse($privateFilesystem->directoryExists($directory));
        } finally {
            if ($privateFilesystem->directoryExists($directory)) {
                $privateFilesystem->deleteDirectory($directory);
            }
        }
    }

    private function getThemeConfig(): StorefrontPluginConfiguration
    {
        $factory = static::getContainer()->get(StorefrontPluginConfigurationFactory::class);

        return $factory->createFromBundle(new ThemeWithFileAssociations());
    }

    private function getThemeConfigWithLabels(): StorefrontPluginConfiguration
    {
        $factory = static::getContainer()->get(StorefrontPluginConfigurationFactory::class);

        return $factory->createFromBundle(new ThemeWithLabels());
    }

    private function getSimpleThemeConfig(): StorefrontPluginConfiguration
    {
        $factory = static::getContainer()->get(StorefrontPluginConfigurationFactory::class);

        return $factory->createFromBundle(new SimpleTheme());
    }

    private function getTheme(StorefrontPluginConfiguration $bundle, bool $withChild = false): ThemeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $bundle->getTechnicalName()));
        $criteria->addAssociation('media');
        $criteria->addAssociation('translations.language.locale');

        if ($withChild) {
            $criteria->addAssociation('dependentThemes');
        }

        $theme = $this->themeRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(ThemeEntity::class, $theme);

        return $theme;
    }

    private function hasTheme(StorefrontPluginConfiguration $bundle): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $bundle->getTechnicalName()));

        return $this->themeRepository->searchIds($criteria, $this->context)->getTotal() > 0;
    }

    private function getMedia(string $fileName): MediaEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        $media = $this->mediaRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(MediaEntity::class, $media);

        return $media;
    }

    private function hasNoMedia(string $fileName): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        $media = $this->mediaRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNull($media);
    }

    // we create a cms-page because it has has the DeleteRestricted flag in media definition
    private function createCmsPage(string $logoId): void
    {
        $manufacturerRepository = static::getContainer()->get('cms_page.repository');
        $manufacturerRepository->create([[
            'name' => 'dummy cms page',
            'previewMediaId' => $logoId,
            'type' => 'page',
            'config' => [],
        ]], $this->context);
    }

    private function addPinkLogoToTheme(StorefrontPluginConfiguration $bundle): void
    {
        $config = $bundle->getThemeConfig();
        $config['fields']['shopwellLogoPink'] = [
            'label' => [
                'en-GB' => 'shopwell_logo_pink',
                'zh-CN' => 'shopwell_logo_pink',
            ],
            'type' => 'media',
            'value' => 'app/storefront/src/assets/image/shopwell_logo_pink.svg',
        ];

        $bundle->setThemeConfig($config);
    }

    private function addPinkLogoToThemeChanged(StorefrontPluginConfiguration $bundle): void
    {
        $config = $bundle->getThemeConfig();
        $config['fields']['shopwellLogoPink'] = [
            'label' => [
                'en-GB' => 'shopwell_logo_pink',
                'zh-CN' => 'shopwell_logo_pink',
            ],
            'type' => 'media',
            'value' => 'app/storefront/src/assets/image/shopwell_logo_pink2.svg',
        ];

        $bundle->setThemeConfig($config);
    }

    private function addPinkLogoToThemeWithoutValue(StorefrontPluginConfiguration $bundle): void
    {
        $config = $bundle->getThemeConfig();
        $config['fields']['shopwellLogoPink'] = [
            'label' => [
                'en-GB' => 'shopwell_logo_pink',
                'zh-CN' => 'shopwell_logo_pink',
            ],
            'type' => 'media',
        ];

        $bundle->setThemeConfig($config);
    }

    private function deleteLanguageForLocale(string $locale): void
    {
        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translationCode.code', $locale));

        $id = $languageRepository->searchIds($criteria, $context)->firstId();

        $languageRepository->delete([
            ['id' => $id],
        ], $context);
    }

    private function changeDefaultLanguageLocale(string $locale): void
    {
        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM));

        $language = $languageRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($language);

        /** @var EntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = static::getContainer()->get('locale.repository');

        $localeRepository->upsert([
            [
                'id' => $language->getTranslationCodeId(),
                'code' => $locale,
            ],
        ], $context);
    }

    private function getTranslationByLocale(string $locale, ThemeTranslationCollection $translations): ThemeTranslationEntity
    {
        $entity = $translations->filter(static function (ThemeTranslationEntity $translation) use ($locale): bool {
            static::assertInstanceOf(LanguageEntity::class, $translation->getLanguage());
            static::assertInstanceOf(LocaleEntity::class, $translation->getLanguage()->getLocale());

            return $locale === $translation->getLanguage()->getLocale()->getCode();
        })->first();

        if ($entity === null) {
            throw new \RuntimeException('Translation not found.');
        }

        return $entity;
    }

    private function getThemeMediaDefaultFolderId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', 'theme'));
        $criteria->addAssociation('defaultFolder');
        $criteria->setLimit(1);
        /** @var MediaFolderCollection $defaultFolder */
        $defaultFolder = $this->mediaFolderRepository->search($criteria, $this->context)->getEntities();

        if ($defaultFolder->count() !== 1 || $defaultFolder->first() === null) {
            throw new \RuntimeException('Default Theme folder does not exist.');
        }

        return $defaultFolder->first()->getId();
    }

    private function createThemeLifecycleService(ThemeRuntimeConfigService $runtimeConfigService): ThemeLifecycleService
    {
        return new ThemeLifecycleService(
            static::getContainer()->get(StorefrontPluginRegistry::class),
            $this->themeRepository,
            $this->mediaRepository,
            $this->mediaFolderRepository,
            static::getContainer()->get('theme_media.repository'),
            static::getContainer()->get(FileSaver::class),
            static::getContainer()->get(FileNameProvider::class),
            $this->themeFilesystemResolver,
            static::getContainer()->get('language.repository'),
            static::getContainer()->get('theme_child.repository'),
            $this->connection,
            static::getContainer()->get(StorefrontPluginConfigurationFactory::class),
            $runtimeConfigService,
            static::getContainer()->get(ThemeSnippetFileWriter::class),
        );
    }
}

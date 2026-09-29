<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopwell\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopwell\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopwell\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopwell\Core\System\Snippet\Service\TranslationUpdater;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationUpdater::class)]
class TranslationUpdaterTest extends TestCase
{
    public function testUpdateInstalledLoadsLocalesRequiringUpdateAndSaves(): void
    {
        $metadata = $this->metadataCollection(['zh-CN' => true, 'es-ES' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->once())
            ->method('load')
            ->with('zh-CN', static::isInstanceOf(Context::class));

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->method('getLocalMetadata')->willReturn($metadata);
        $store->method('getUpdatedLocalMetadata')->willReturn($metadata);
        $store->expects($this->once())->method('save')->with($metadata);

        $result = (new TranslationUpdater($loader, $store))->updateInstalled(Context::createCLIContext());

        static::assertSame(['zh-CN'], $result->updated);
        static::assertSame(['es-ES'], $result->skipped);
    }

    public function testUpdateInstalledSkipsLoadAndSaveWhenNothingRequiresUpdate(): void
    {
        $metadata = $this->metadataCollection(['zh-CN' => false, 'es-ES' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->never())->method('load');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->method('getLocalMetadata')->willReturn($metadata);
        $store->method('getUpdatedLocalMetadata')->willReturn($metadata);
        $store->expects($this->never())->method('save');

        $result = (new TranslationUpdater($loader, $store))->updateInstalled(Context::createCLIContext());

        static::assertSame([], $result->updated);
        static::assertSame(['zh-CN', 'es-ES'], $result->skipped);
    }

    public function testUpdateInstalledRefreshesAllInstalledLocales(): void
    {
        $metadata = $this->metadataCollection(['zh-CN' => true]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->once())->method('load')->with('zh-CN');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->method('getLocalMetadata')->willReturn($metadata);
        $store->expects($this->once())->method('getUpdatedLocalMetadata')->with(null)->willReturn($metadata);
        $store->expects($this->once())->method('save')->with($metadata);

        $result = (new TranslationUpdater($loader, $store))->updateInstalled(Context::createCLIContext());

        static::assertSame(['zh-CN'], $result->updated);
    }

    public function testUpdateInstalledRestrictsRefreshToGivenLocales(): void
    {
        $installed = $this->metadataCollection(['zh-CN' => false, 'es-ES' => false]);
        $updated = $this->metadataCollection(['zh-CN' => true, 'es-ES' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->once())->method('load')->with('zh-CN');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->method('getLocalMetadata')->willReturn($installed);
        $store->expects($this->once())->method('getUpdatedLocalMetadata')->with(['zh-CN'])->willReturn($updated);
        $store->expects($this->once())->method('save')->with($updated);

        $result = (new TranslationUpdater($loader, $store))->updateInstalled(Context::createCLIContext(), ['zh-CN']);

        static::assertSame(['zh-CN'], $result->updated);
        static::assertSame(['es-ES'], $result->skipped);
    }

    public function testUpdateInstalledDoesNothingWhenGivenLocalesAreNotInstalled(): void
    {
        $installed = $this->metadataCollection(['zh-CN' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->never())->method('load');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->method('getLocalMetadata')->willReturn($installed);
        $store->expects($this->never())->method('getUpdatedLocalMetadata');
        $store->expects($this->never())->method('save');

        $result = (new TranslationUpdater($loader, $store))->updateInstalled(Context::createCLIContext(), ['fr-FR']);

        static::assertSame([], $result->updated);
        static::assertSame([], $result->skipped);
    }

    public function testUpdateInstalledDoesNothingWhenNoLocaleInstalled(): void
    {
        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->expects($this->never())->method('load');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->method('getLocalMetadata')->willReturn(new MetadataCollection());
        $store->expects($this->never())->method('getUpdatedLocalMetadata');
        $store->expects($this->never())->method('save');

        $result = (new TranslationUpdater($loader, $store))->updateInstalled(Context::createCLIContext());

        static::assertSame([], $result->updated);
        static::assertSame([], $result->skipped);
    }

    public function testPlanInstallPartitionsTheRequestedLocales(): void
    {
        $metadata = $this->metadataCollection(['zh-CN' => true, 'es-ES' => false, 'it-IT' => false]);

        $loader = static::createStub(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')
            ->willReturnCallback(static fn (string $locale) => \in_array($locale, ['es-ES', 'fr-FR'], true));

        $plan = (new TranslationUpdater($loader, static::createStub(TranslationMetadataStore::class)))
            ->planInstall(['zh-CN', 'es-ES', 'fr-FR', 'nl-NL', 'it-IT'], $metadata);

        // zh-CN has something newer, it-IT is current but has lost its files
        static::assertSame(['zh-CN', 'it-IT'], $plan->localesToDownload);
        // es-ES is current and present, fr-FR has files without a metadata entry
        static::assertSame(['es-ES', 'fr-FR'], $plan->localesToLink);
        // nl-NL is neither offered nor present
        static::assertSame(['nl-NL'], $plan->unavailableLocales);
        static::assertFalse($plan->nothingCanBeInstalled());
    }

    public function testPlanInstallReportsNothingInstallableWhenNoLocaleIsOfferedOrPresent(): void
    {
        $loader = static::createStub(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')->willReturn(false);

        $plan = (new TranslationUpdater($loader, static::createStub(TranslationMetadataStore::class)))
            ->planInstall(['zh-CN', 'es-ES'], new MetadataCollection());

        static::assertSame(['zh-CN', 'es-ES'], $plan->unavailableLocales);
        static::assertTrue($plan->nothingCanBeInstalled());
    }

    public function testPlanOfflineInstallSplitsByFilePresenceWithoutTouchingTheMetadata(): void
    {
        $loader = static::createStub(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')
            ->willReturnCallback(static fn (string $locale) => $locale === 'es-ES');

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->expects($this->never())->method('getUpdatedLocalMetadata');
        $store->expects($this->never())->method('getLocalMetadata');

        $plan = (new TranslationUpdater($loader, $store))->planOfflineInstall(['zh-CN', 'es-ES']);

        static::assertSame([], $plan->localesToDownload);
        static::assertSame(['es-ES'], $plan->localesToLink);
        static::assertSame(['zh-CN'], $plan->unavailableLocales);
    }

    public function testInstallDownloadsThenLinksAndLeavesPersistingToTheCaller(): void
    {
        $metadata = $this->metadataCollection(['zh-CN' => true, 'es-ES' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')->willReturn(true);
        $loader->expects($this->once())->method('download')->with('zh-CN');

        $linked = [];
        $loader->expects($this->exactly(2))
            ->method('link')
            ->willReturnCallback(static function (string $locale, Context $context, bool $activate) use (&$linked): void {
                static::assertTrue($activate);
                $linked[] = $locale;
            });

        $store = $this->createMock(TranslationMetadataStore::class);
        $store->expects($this->never())->method('save');

        $updater = new TranslationUpdater($loader, $store);
        $result = $updater->install($updater->planInstall(['zh-CN', 'es-ES'], $metadata), Context::createCLIContext());

        static::assertSame(['zh-CN', 'es-ES'], $linked);
        static::assertSame(['zh-CN'], $result->updated);
        static::assertSame(['es-ES'], $result->skipped);
    }

    public function testInstallReportsEveryLocaleToTheProgressCallback(): void
    {
        $metadata = $this->metadataCollection(['zh-CN' => true, 'es-ES' => false]);

        $loader = static::createStub(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')->willReturn(true);

        $updater = new TranslationUpdater($loader, static::createStub(TranslationMetadataStore::class));

        $reported = [];
        $updater->install(
            $updater->planInstall(['zh-CN', 'es-ES'], $metadata),
            Context::createCLIContext(),
            true,
            static function (string $locale) use (&$reported): void {
                $reported[] = $locale;
            },
        );

        static::assertSame(['zh-CN', 'es-ES'], $reported);
    }

    public function testInstallPassesActivateFalseToTheLoader(): void
    {
        $metadata = $this->metadataCollection(['zh-CN' => false]);

        $loader = $this->createMock(AbstractTranslationLoader::class);
        $loader->method('hasTranslationFiles')->willReturn(true);
        $loader->expects($this->once())->method('link')->with('zh-CN', static::isInstanceOf(Context::class), false);

        $updater = new TranslationUpdater($loader, static::createStub(TranslationMetadataStore::class));
        $updater->install($updater->planInstall(['zh-CN'], $metadata), Context::createCLIContext(), false);
    }

    /**
     * @param array<string, bool> $localesRequiringUpdate keyed by locale, value = isUpdateRequired
     */
    private function metadataCollection(array $localesRequiringUpdate): MetadataCollection
    {
        $collection = new MetadataCollection();
        foreach ($localesRequiringUpdate as $locale => $requiresUpdate) {
            $entry = MetadataEntry::create([
                'locale' => $locale,
                'updatedAt' => '2025-08-07T11:26:28.974+00:00',
                'progress' => 100,
            ]);

            if ($requiresUpdate) {
                $entry->markForUpdate();
            }

            $collection->add($entry);
        }

        return $collection;
    }
}

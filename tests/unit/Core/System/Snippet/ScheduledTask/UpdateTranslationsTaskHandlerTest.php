<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Core\System\Snippet\DataTransfer\TranslationUpdate\TranslationUpdateResult;
use Shopwell\Core\System\Snippet\ScheduledTask\UpdateTranslationsTaskHandler;
use Shopwell\Core\System\Snippet\Service\TranslationUpdater;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(UpdateTranslationsTaskHandler::class)]
class UpdateTranslationsTaskHandlerTest extends TestCase
{
    public function testRunUpdatesOnlyTheLocalesOfFlaggedLanguages(): void
    {
        $languages = new LanguageCollection([
            $this->language('fr-FR', 'id-1'),
            $this->language('zh-CN', 'id-2'),
            // duplicate locale must be deduplicated, missing locale must be filtered out
            $this->language('fr-FR', 'id-3'),
            $this->language(null, 'id-4'),
        ]);

        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($languages): LanguageCollection {
                $filter = $criteria->getFilters()[0] ?? null;
                static::assertInstanceOf(EqualsFilter::class, $filter);
                static::assertSame('translationAutoUpdate', $filter->getField());
                static::assertTrue($filter->getValue());

                return $languages;
            },
        ]);

        $updater = $this->createMock(TranslationUpdater::class);
        $updater->expects($this->once())
            ->method('updateInstalled')
            ->with(static::isInstanceOf(Context::class), ['fr-FR', 'zh-CN'])
            ->willReturn(new TranslationUpdateResult());

        $this->handler($updater, $languageRepository)->run();
    }

    public function testRunSkipsWhenNoLanguageIsFlagged(): void
    {
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([
            static fn (): LanguageCollection => new LanguageCollection(),
        ]);

        $updater = $this->createMock(TranslationUpdater::class);
        $updater->expects($this->never())->method('updateInstalled');

        $this->handler($updater, $languageRepository)->run();
    }

    private function language(?string $localeCode, string $id): LanguageEntity
    {
        $language = new LanguageEntity();
        $language->setId($id);
        $language->setUniqueIdentifier($id);

        if ($localeCode !== null) {
            $locale = new LocaleEntity();
            $locale->setId($localeCode);
            $locale->setUniqueIdentifier($localeCode);
            $locale->setCode($localeCode);
            $language->setLocale($locale);
        }

        return $language;
    }

    /**
     * @param StaticEntityRepository<LanguageCollection> $languageRepository
     */
    private function handler(
        TranslationUpdater&MockObject $updater,
        StaticEntityRepository $languageRepository,
    ): UpdateTranslationsTaskHandler {
        return new UpdateTranslationsTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $updater,
            $languageRepository,
        );
    }
}

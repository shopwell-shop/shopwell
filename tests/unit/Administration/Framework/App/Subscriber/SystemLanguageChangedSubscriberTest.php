<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Administration\Framework\App\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Framework\App\Subscriber\SystemLanguageChangedSubscriber;
use Shopwell\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopwell\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Maintenance\System\Service\SystemLanguageChangeEvent;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Core\System\Locale\LocaleException;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemLanguageChangedSubscriber::class)]
class SystemLanguageChangedSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [SystemLanguageChangeEvent::class => 'onSystemLanguageChanged'],
            SystemLanguageChangedSubscriber::getSubscribedEvents()
        );
    }

    public function testDoesNotRunIfNoSnippetsExist(): void
    {
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection()]);

        $localeRepository = new StaticEntityRepository([]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            'en-GB',
            'en-US',
        ));

        static::assertSame([], $localeRepository->searchedCriteria);
    }

    public function testOnSystemLanguageChangedThrowsExceptionWhenNewLocaleDoesNotExist(): void
    {
        $localeRepository = new StaticEntityRepository([[]]);
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            (new AppAdministrationSnippetEntity())->assign([
                'id' => 'snippet-id',
                'appId' => 'app-id',
            ]),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $this->expectExceptionObject(LocaleException::localeDoesNotExists('fr-DE'));

        $subscriber->onSystemLanguageChanged(
            new SystemLanguageChangeEvent(
                'previous-language-id',
                'fr-DE',
                'zh-CN'
            )
        );
    }

    public function testDoesNotUpdateSnippetsIfSystemLanguageIsChangedFromEnGbToZhCn(): void
    {
        $previousLocale = $this->createLocale('en-GB');
        $newLocale = $this->createLocale('zh-CN');

        $localeRepository = new StaticEntityRepository([
            [$previousLocale->getId()],
            [$newLocale->getId()],
        ]);

        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $this->createSnippet('app-one-id', $previousLocale->getId()),
            $this->createSnippet('app-one-id', 'other-locale-id'),
            $this->createSnippet('app-two-id', $previousLocale->getId()),
            $this->createSnippet('app-two-id', 'other-locale-id'),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        static::assertCount(0, $snippetRepository->creates);
    }

    #[DataProvider('localeCodes')]
    public function testUpdatesSnippetsForPreviousLocaleWithPreviousLocaleId(string $locale): void
    {
        $previousLocale = $this->createLocale('en-GB');
        $newLocale = $this->createLocale($locale);

        $localeRepository = new StaticEntityRepository([
            [$previousLocale->getId()],
            [$newLocale->getId()],
        ]);

        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $snippetOneToUpdate = $this->createSnippet('app-one-id', $newLocale->getId()),
            $this->createSnippet('app-one-id', 'other-locale-id'),
            $snippetTwoToUpdate = $this->createSnippet('app-two-id', $newLocale->getId()),
            $this->createSnippet('app-two-id', 'other-locale-id'),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        static::assertSame([
            'id' => $snippetOneToUpdate->getId(),
            'localeId' => $previousLocale->getId(),
        ], $snippetRepository->updates[0][0]);

        static::assertSame([
            'id' => $snippetTwoToUpdate->getId(),
            'localeId' => $previousLocale->getId(),
        ], $snippetRepository->updates[1][0]);
    }

    #[DataProvider('localeCodes')]
    public function testUpdatesSnippetsForNewLocaleWithNewLocaleId(string $locale): void
    {
        $previousLocale = $this->createLocale('en-GB');
        $newLocale = $this->createLocale($locale);

        $localeRepository = new StaticEntityRepository([
            [$previousLocale->getId()],
            [$newLocale->getId()],
        ]);

        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $this->createSnippet('app-one-id', $previousLocale->getId()),
            $this->createSnippet('app-two-id', $previousLocale->getId()),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        static::assertCount(2, $snippetRepository->updates);
    }

    public static function localeCodes(): \Generator
    {
        yield ['en-US'];
        yield ['it-IT'];
        yield ['es-ES'];
        yield ['fr-FR'];
    }

    private function createLocale(string $code): LocaleEntity
    {
        return (new LocaleEntity())->assign([
            'id' => Uuid::randomHex(),
            'code' => $code,
        ]);
    }

    private function createSnippet(string $appId, string $localeId): AppAdministrationSnippetEntity
    {
        return (new AppAdministrationSnippetEntity())->assign([
            'id' => Uuid::randomHex(),
            'appId' => $appId,
            'localeId' => $localeId,
            'value' => 'snippet-value',
        ]);
    }
}

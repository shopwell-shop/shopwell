<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Administration\Framework\App\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopwell\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Maintenance\System\Service\ShopConfigurator;
use Shopwell\Core\Maintenance\System\Service\SystemLanguageChangeEvent;

/**
 * @internal
 */
#[Package('framework')]
class SystemLanguageChangedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    /**
     * @var EntityRepository<AppAdministrationSnippetCollection>
     */
    private EntityRepository $snippetRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->snippetRepository = $this->getContainer()->get('app_administration_snippet.repository');
    }

    #[DataProvider('localeCodes')]
    public function testUpdatesSnippetsAfterSystemLanguageChanged(string $localeCode): void
    {
        $previousSystemLocale = $this->getCurrentSystemLocale();
        static::assertSame('en-GB', $previousSystemLocale['code']);

        $appOne = $this->createAppWithSnippets('SwagAppOne', $previousSystemLocale['id']);
        $this->createAppWithSnippets('SwagAppTwo');
        $appThree = $this->createAppWithSnippets('SwagAppThree', $previousSystemLocale['id']);
        $this->createAppWithSnippets('SwagAppFour');

        $snippetsBefore = $this->snippetRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $snippetsBefore);
        self::assertSnippetExistsForAppAndLocale($snippetsBefore, $appOne->getId(), $previousSystemLocale['id']);
        self::assertSnippetExistsForAppAndLocale($snippetsBefore, $appThree->getId(), $previousSystemLocale['id']);

        $previousLocaleCode = '';
        $newLocaleCode = '';
        $this->getContainer()->get('event_dispatcher')->addListener(
            SystemLanguageChangeEvent::class,
            static function (SystemLanguageChangeEvent $event) use (&$previousLocaleCode, &$newLocaleCode): void {
                $previousLocaleCode = $event->previousLocaleCode;
                $newLocaleCode = $event->newLocaleCode;
            }
        );

        $this->getContainer()->get(ShopConfigurator::class)->setDefaultLanguage($localeCode);

        static::assertSame('en-GB', $previousLocaleCode);
        static::assertSame($localeCode, $newLocaleCode);

        $previousLocale = $this->getLocale($previousLocaleCode);

        $snippetsAfter = $this->snippetRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $snippetsAfter);
        self::assertSnippetExistsForAppAndLocale($snippetsAfter, $appOne->getId(), $previousLocale['id']);
        self::assertSnippetExistsForAppAndLocale($snippetsAfter, $appThree->getId(), $previousLocale['id']);
    }

    public function testDoesNoUpdateSnippetsAfterSystemLanguageChangedFromEnGbToZhCn(): void
    {
        $previousSystemLocale = $this->getCurrentSystemLocale();
        static::assertSame('en-GB', $previousSystemLocale['code']);

        $appOne = $this->createAppWithSnippets('SwagAppOne', $previousSystemLocale['id']);
        $this->createAppWithSnippets('SwagAppTwo');
        $appThree = $this->createAppWithSnippets('SwagAppThree', $previousSystemLocale['id']);
        $this->createAppWithSnippets('SwagAppFour');

        $snippetsBefore = $this->snippetRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $snippetsBefore);
        self::assertSnippetExistsForAppAndLocale($snippetsBefore, $appOne->getId(), $previousSystemLocale['id']);
        self::assertSnippetExistsForAppAndLocale($snippetsBefore, $appThree->getId(), $previousSystemLocale['id']);

        $this->getContainer()->get(ShopConfigurator::class)->setDefaultLanguage('zh-CN');

        $newSystemLocale = $this->getCurrentSystemLocale();
        static::assertSame('zh-CN', $newSystemLocale['code']);

        $snippetsAfter = $this->snippetRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $snippetsAfter);
        self::assertSnippetExistsForAppAndLocale($snippetsAfter, $appOne->getId(), $previousSystemLocale['id']);
        self::assertSnippetExistsForAppAndLocale($snippetsAfter, $appThree->getId(), $previousSystemLocale['id']);
    }

    public static function localeCodes(): \Generator
    {
        yield ['en-US'];
        yield ['it-IT'];
        yield ['es-ES'];
        yield ['fr-FR'];
    }

    /**
     * @return array{code: string, id: string}
     */
    private function getCurrentSystemLocale(): array
    {
        $currentSystemLocale = $this->connection
            ->executeQuery(
                'SELECT locale.code, LOWER(HEX(locale.id)) AS id FROM locale INNER JOIN language ON language.locale_id = locale.id WHERE language.id = :languageId',
                ['languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
            )->fetchAssociative();

        if ($currentSystemLocale === false) {
            static::fail('Could not fetch current system locale');
        }

        /** @var array{code: string, id: string} $currentSystemLocale */
        return $currentSystemLocale;
    }

    /**
     * @return array{code: string, id: string}
     */
    private function getLocale(string $code): array
    {
        $locale = $this->connection
            ->executeQuery(
                'SELECT code, LOWER(HEX(locale.id)) AS id FROM locale WHERE code = :code',
                ['code' => $code]
            )->fetchAssociative();

        if ($locale === false) {
            static::fail(\sprintf('Could not fetch locale with code "%s"', $code));
        }

        /** @var array{code: string, id: string} $locale */
        return $locale;
    }

    private function createAppWithSnippets(string $name, ?string $localeId = null): AppEntity
    {
        $this->appRepository->create([
            [
                'id' => $id = Uuid::randomHex(),
                'name' => $name,
                'active' => true,
                'appVersion' => '1.0.0',
                'author' => 'Shopwell AG',
                'label' => [
                    'en-GB' => 'Test App',
                    'en-US' => 'Test App',
                    'zh-CN' => 'Test App',
                ],
                'path' => 'path',
                'version' => '1.0.0',
                'integration' => [
                    'id' => Uuid::randomHex(),
                    'label' => $name . ' Integration',
                    'accessKey' => Uuid::randomHex(),
                    'secretAccessKey' => Uuid::randomHex(),
                ],
                'aclRole' => [
                    'id' => Uuid::randomHex(),
                    'name' => $name . ' ACL Role',
                ],
            ],
        ], $this->context);

        if ($localeId !== null) {
            $this->snippetRepository->create([
                [
                    'appId' => $id,
                    'localeId' => $localeId,
                    'value' => json_encode([]),
                ],
            ], $this->context);
        }

        $app = $this->appRepository->search(new Criteria([$id]), $this->context)->getEntities()->first();
        \assert($app instanceof AppEntity);

        return $app;
    }

    private static function assertSnippetExistsForAppAndLocale(
        AppAdministrationSnippetCollection $snippets,
        string $appId,
        string $localeId
    ): void {
        static::assertInstanceOf(
            AppAdministrationSnippetEntity::class,
            $snippets->filter(static fn (AppAdministrationSnippetEntity $snippet) => $snippet->getAppId() === $appId && $snippet->getLocaleId() === $localeId)->first(),
        );
    }
}

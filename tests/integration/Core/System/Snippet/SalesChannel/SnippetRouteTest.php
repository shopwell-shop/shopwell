<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Snippet\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Snippet\SnippetException;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('store-api')]
class SnippetRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private string $enGbSnippetSetId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $enGbSnippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        static::assertNotNull($enGbSnippetSetId);
        $this->enGbSnippetSetId = $enGbSnippetSetId;

        $zhCnSnippetSetId = $this->getSnippetSetIdForLocale('zh-CN');
        static::assertNotNull($zhCnSnippetSetId);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => $this->getZhCnLanguageId()],
            ],
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $enGbSnippetSetId,
                    'url' => 'http://example.com',
                ],
                [
                    'languageId' => $this->getZhCnLanguageId(),
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $zhCnSnippetSetId,
                    'url' => 'http://example.com/zh',
                ],
            ],
        ]);
    }

    public function testReturnsResolvedSnippetsForTheContextLanguage(): void
    {
        $this->createSnippet('myCustom.test.key', 'Custom value');

        $this->browser->request('GET', '/store-api/snippet');

        $response = $this->getJsonResponse();

        static::assertSame('snippet_set_result_list', $response['apiAlias']);
        // without languageIds the list contains exactly one set: the context language
        static::assertCount(1, $response['sets']);

        $set = $response['sets'][0];
        static::assertSame('snippet_set_result', $set['apiAlias']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $set['languageId']);
        static::assertSame('en-GB', $set['locale']);
        static::assertSame($this->enGbSnippetSetId, $set['snippetSetId']);
        static::assertIsString($set['hash']);
        static::assertNotSame('', $set['hash']);

        static::assertIsArray($set['snippets']);
        static::assertNotEmpty($set['snippets']);
        // the database override of the snippet set is part of the resolved map
        static::assertSame('Custom value', $set['snippets']['myCustom.test.key']);

        $etag = $this->browser->getResponse()->headers->get('ETag');
        static::assertSame('"' . $set['hash'] . '"', $etag);
    }

    public function testPrefixesLimitTheResultToWholeNamespaces(): void
    {
        $this->createSnippet('myPrefix.inside.key', 'Inside');
        $this->createSnippet('myPrefixOther.key', 'Outside');

        $this->browser->request('GET', '/store-api/snippet?prefixes=myPrefix');

        $set = $this->getJsonResponse()['sets'][0];

        static::assertSame('Inside', $set['snippets']['myPrefix.inside.key'] ?? null);
        static::assertArrayNotHasKey('myPrefixOther.key', $set['snippets']);

        foreach (array_keys($set['snippets']) as $key) {
            static::assertTrue(
                $key === 'myPrefix' || str_starts_with((string) $key, 'myPrefix.'),
                \sprintf('Key "%s" does not belong to the requested namespace', $key)
            );
        }

        // a trailing dot is optional and must not change the content hash
        $this->browser->request('GET', '/store-api/snippet?prefixes=myPrefix.');
        $setWithTrailingDot = $this->getJsonResponse()['sets'][0];

        static::assertSame($set['hash'], $setWithTrailingDot['hash']);
        static::assertSame($set['snippets'], $setWithTrailingDot['snippets']);
    }

    public function testEtagRevalidationReturnsNotModified(): void
    {
        $this->browser->request('GET', '/store-api/snippet');

        $etag = $this->browser->getResponse()->headers->get('ETag');
        static::assertIsString($etag);

        $this->browser->request('GET', '/store-api/snippet', [], [], ['HTTP_IF_NONE_MATCH' => $etag]);

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        static::assertSame('', (string) $response->getContent());
    }

    public function testMultipleLanguagesReturnOneSetPerLanguage(): void
    {
        $languageIds = implode(',', [Defaults::LANGUAGE_SYSTEM, $this->getZhCnLanguageId()]);

        $this->browser->request('GET', '/store-api/snippet?languageIds=' . $languageIds);

        $response = $this->getJsonResponse();

        static::assertSame('snippet_set_result_list', $response['apiAlias']);
        static::assertCount(2, $response['sets']);

        $locales = array_column($response['sets'], 'locale', 'languageId');
        static::assertSame('en-GB', $locales[Defaults::LANGUAGE_SYSTEM]);
        static::assertSame('zh-CN', $locales[$this->getZhCnLanguageId()]);

        foreach ($response['sets'] as $set) {
            static::assertSame('snippet_set_result', $set['apiAlias']);
            static::assertNotEmpty($set['snippets']);
        }
    }

    public function testLanguagesSharingOneLocaleResolveTheirOwnSnippetSets(): void
    {
        $context = Context::createDefaultContext();
        $zhCnLanguageId = $this->getZhCnLanguageId();

        $zhCnSnippetSetId = $this->getSnippetSetIdForLocale('zh-CN');
        static::assertNotNull($zhCnSnippetSetId);

        // a child language without an own translation code inherits the parent's locale (zh-CN)
        $informalLanguageId = Uuid::randomHex();
        static::getContainer()->get('language.repository')->create([
            [
                'id' => $informalLanguageId,
                'name' => '简体中文（非正式）',
                'parentId' => $zhCnLanguageId,
                'localeId' => $this->getLocaleIdByCode('zh-CN'),
            ],
        ], $context);

        // an own snippet set for the informal variant, same iso as the default zh-CN set
        $informalSnippetSetId = Uuid::randomHex();
        static::getContainer()->get('snippet_set.repository')->create([
            [
                'id' => $informalSnippetSetId,
                'name' => '简体中文（非正式）',
                'baseFile' => 'messages.zh-CN',
                'iso' => 'zh-CN',
            ],
        ], $context);

        static::getContainer()->get('snippet.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'translationKey' => 'myShared.locale.key',
                'value' => 'Formal value',
                'author' => 'testAuthor',
                'setId' => $zhCnSnippetSetId,
            ],
            [
                'id' => Uuid::randomHex(),
                'translationKey' => 'myShared.locale.key',
                'value' => 'Informal value',
                'author' => 'testAuthor',
                'setId' => $informalSnippetSetId,
            ],
        ], $context);

        $browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel-shared-locale'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => $zhCnLanguageId],
                ['id' => $informalLanguageId],
            ],
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->enGbSnippetSetId,
                    'url' => 'http://shared-locale.example.com',
                ],
                [
                    'languageId' => $zhCnLanguageId,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $zhCnSnippetSetId,
                    'url' => 'http://shared-locale.example.com/zh',
                ],
                [
                    'languageId' => $informalLanguageId,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $informalSnippetSetId,
                    'url' => 'http://shared-locale.example.com/zh-informal',
                ],
            ],
        ]);

        $browser->request(
            'GET',
            '/store-api/snippet?languageIds=' . implode(',', [$zhCnLanguageId, $informalLanguageId])
        );

        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(2, $response['sets']);
        $setsByLanguage = array_column($response['sets'], null, 'languageId');

        // both languages resolve to locale zh-CN, but each must keep its own snippet set and overrides
        static::assertSame('zh-CN', $setsByLanguage[$zhCnLanguageId]['locale']);
        static::assertSame('zh-CN', $setsByLanguage[$informalLanguageId]['locale']);
        static::assertSame($zhCnSnippetSetId, $setsByLanguage[$zhCnLanguageId]['snippetSetId']);
        static::assertSame($informalSnippetSetId, $setsByLanguage[$informalLanguageId]['snippetSetId']);
        static::assertSame('Formal value', $setsByLanguage[$zhCnLanguageId]['snippets']['myShared.locale.key']);
        static::assertSame('Informal value', $setsByLanguage[$informalLanguageId]['snippets']['myShared.locale.key']);
    }

    public function testFailsForALanguageNotAssignedToTheSalesChannel(): void
    {
        $this->browser->request('GET', '/store-api/snippet?languageIds=' . Uuid::randomHex());

        $response = $this->getJsonResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame(
            SnippetException::SNIPPET_LANGUAGE_NOT_AVAILABLE_IN_SALES_CHANNEL,
            $response['errors'][0]['code']
        );
    }

    private function getLocaleIdByCode(string $code): string
    {
        $localeId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `locale` WHERE `code` = :code',
            ['code' => $code]
        );
        static::assertIsString($localeId);

        return $localeId;
    }

    private function createSnippet(string $translationKey, string $value): void
    {
        static::getContainer()->get('snippet.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'translationKey' => $translationKey,
                'value' => $value,
                'author' => 'testAuthor',
                'setId' => $this->enGbSnippetSetId,
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonResponse(): array
    {
        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Framework\Routing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Seo\SeoResolver;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Util\AccessKeyHelper;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RequestTransformer as CoreRequestTransformer;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\SalesChannelRequest;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Storefront\Framework\Routing\DomainLoader;
use Shopwell\Storefront\Framework\Routing\Exception\SalesChannelMappingException;
use Shopwell\Storefront\Framework\Routing\RequestTransformer;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopwell\Storefront\Test\Framework\Routing\Helper\ExpectedRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @phpstan-type SalesChannel array{id: string, name: string, active: bool, languages: array{id: string}[], domains: array{id: string, url: string, languageId: string, currencyId: string, snippetSetId: string}[]}
 */
#[Package('discovery')]
class RequestTransformerTest extends TestCase
{
    use IntegrationTestBehaviour;

    final public const LOCALE_ZH_CN_ISO = 'zh-CN';
    final public const LOCALE_EN_GB_ISO = 'en-GB';

    private RequestTransformer $requestTransformer;

    private string $zhLanguageId;

    protected function setUp(): void
    {
        /** @var list<string> $registeredApiPrefixes */
        $registeredApiPrefixes = static::getContainer()->getParameter('shopwell.routing.registered_api_prefixes');

        $this->requestTransformer = new RequestTransformer(
            new CoreRequestTransformer(),
            static::getContainer()->get(SeoResolver::class),
            $registeredApiPrefixes,
            static::getContainer()->get(DomainLoader::class)
        );

        $this->zhLanguageId = $this->getZhCnLanguageId();
    }

    /**
     * @param list<SalesChannel> $salesChannels
     * @param list<ExpectedRequest> $requests
     */
    #[DataProvider('domainProvider')]
    public function testDomainResolving(array $salesChannels, array $requests): void
    {
        $this->createSalesChannels($salesChannels);

        $snippetSetEN = $this->getSnippetSetIdForLocale(self::LOCALE_EN_GB_ISO);
        $snippetSetZH = $this->getSnippetSetIdForLocale(self::LOCALE_ZH_CN_ISO);

        foreach ($requests as $expectedRequest) {
            if ($expectedRequest->exception) {
                $exception = $expectedRequest->exception;

                $this->expectException($exception);
            }

            $request = Request::create($expectedRequest->url);

            $resolved = $this->requestTransformer->transform($request);

            $expectedSnippetSetId = $expectedRequest->snippetLanguageCode === 'zh-CN' ? $snippetSetZH : $snippetSetEN;
            $expectedLanguageId = $expectedRequest->languageCode === 'zh-CN' ? $this->zhLanguageId : Defaults::LANGUAGE_SYSTEM;

            static::assertSame($expectedRequest->salesChannelId, $resolved->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));

            static::assertSame($expectedRequest->domainId, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID));
            static::assertSame($expectedRequest->isStorefrontRequest, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST));
            static::assertSame($expectedRequest->locale, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE));
            static::assertSame($expectedRequest->currency, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
            static::assertSame($expectedSnippetSetId, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID));
            static::assertSame($expectedRequest->baseUrl, $resolved->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL), $expectedRequest->url);
            static::assertSame($expectedRequest->resolvedUrl, $resolved->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI));
            static::assertSame($expectedLanguageId, $resolved->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        }
    }

    /**
     * @return iterable<string, array{0: list<SalesChannel>, 1: list<ExpectedRequest>}>
     */
    public static function domainProvider(): iterable
    {
        $chineseId = Uuid::randomHex();
        $englishId = Uuid::randomHex();
        $cnUkId = Uuid::randomHex();
        $cnUkId2 = Uuid::randomHex();

        $cnDomainId = Uuid::randomHex();
        $ukDomainId = Uuid::randomHex();

        $cnDomainId2 = Uuid::randomHex();
        $ukDomainId2 = Uuid::randomHex();

        yield 'single' => [
            [self::getChineseSalesChannel($chineseId, $cnDomainId, 'http://chinese.test')],
            [
                new ExpectedRequest('http://chinese.test', '', '/', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/', '', '/', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/foobar', '', '/foobar', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test//foobar', '', '/foobar', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
            ],
        ];
        yield 'two' => [
            [
                self::getChineseSalesChannel($chineseId, $cnDomainId, 'http://chinese.test'),
                self::getEnglishSalesChannel($englishId, $ukDomainId, 'http://english.test'),
            ],
            [
                new ExpectedRequest('http://chinese.test', '', '/', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/', '', '/', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/foobar', '', '/foobar', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://english.test', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/foobar', '', '/foobar', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://english.test/navigation/1', '', '/navigation/1', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://chinese.test/navigation/1', '', '/navigation/1', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
            ],
        ];
        yield 'single-with-cn-and-uk-domain' => [
            [
                self::getSalesChannelWithCnAndUkDomain($cnUkId, $cnDomainId, 'http://chinese.test', $ukDomainId, 'http://english.test'),
            ],
            [
                new ExpectedRequest('http://chinese.test', '', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/', '', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/foobar', '', '/foobar', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://english.test', '', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/', '', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/foobar', '', '/foobar', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'single-with-cn-and-uk-domain-with-port' => [
            [
                self::getSalesChannelWithCnAndUkDomain($cnUkId, $cnDomainId, 'http://base.test:1337', $ukDomainId, 'http://base.test:31337'),
            ],
            [
                new ExpectedRequest('http://base.test:1337', '', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://base.test:1337/', '', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://base.test:1337/foobar', '', '/foobar', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://base.test:31337', '', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:31337/', '', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:31337/foobar', '', '/foobar', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'single-with-cn-and-uk-domain-with-same-port-different-path' => [
            [
                self::getSalesChannelWithCnAndUkDomain($cnUkId, $cnDomainId, 'http://base.test:1337/foo', $ukDomainId, 'http://base.test:1337/bar'),
            ],
            [
                new ExpectedRequest('http://base.test:1337/foo', '/foo', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://base.test:1337/foo/', '/foo', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://base.test:1337/foo/foobar', '/foo', '/foobar', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://base.test:1337/bar', '/bar', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:1337/bar/', '/bar', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:1337/bar/foobar', '/bar', '/foobar', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'two-domains-same-host-different-path' => [
            [
                self::getSalesChannelWithCnAndUkDomain($cnUkId, $cnDomainId, 'http://saleschannel.test/zh', $ukDomainId, 'http://saleschannel.test/en'),
            ],
            [
                new ExpectedRequest('http://saleschannel.test/zh', '/zh', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/zh/', '/zh', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/zh/foobar', '/zh', '/foobar', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://saleschannel.test/en', '/en', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/', '/en', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/foobar', '/en', '/foobar', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/zh/navigation/1', '/zh', '/navigation/1', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/en/navigation/1', '/en', '/navigation/1', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/zh/zh/navigation/1', '/zh', '/zh/navigation/1', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/en/en/navigation/1', '/en', '/en/navigation/1', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'two-scs-same-host-different-sub-path-unsorted' => [
            [
                self::getSalesChannelWithCnAndUkDomain($cnUkId, $cnDomainId, 'http://saleschannel.test/zh', $ukDomainId, 'http://saleschannel.test/en'),
                self::getSalesChannelWithCnAndUkDomain($cnUkId2, $cnDomainId2, 'http://saleschannel.test/subdir/zh', $ukDomainId2, 'http://saleschannel.test/subdir/en'),
            ],
            [
                new ExpectedRequest('http://saleschannel.test/zh', '/zh', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/zh/', '/zh', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/zh/foobar', '/zh', '/foobar', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://saleschannel.test/subdir/en', '/subdir/en', '/', $ukDomainId2, $cnUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/', '/subdir/en', '/', $ukDomainId2, $cnUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/foobar', '/subdir/en', '/foobar', $ukDomainId2, $cnUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/en', '/en', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/', '/en', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/foobar', '/en', '/foobar', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/zh/navigation/1', '/zh', '/navigation/1', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/navigation/1', '/subdir/en', '/navigation/1', $ukDomainId2, $cnUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/zh/zh/navigation/1', '/zh', '/zh/navigation/1', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/en/navigation/1', '/subdir/en', '/en/navigation/1', $ukDomainId2, $cnUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'two-domains-same-host-extended-path' => [
            [
                self::getSalesChannelWithCnAndUkDomain($cnUkId, $cnDomainId, 'http://saleschannel.test/zh', $ukDomainId, 'http://saleschannel.test'),
            ],
            [
                new ExpectedRequest('http://saleschannel.test/zh', '/zh', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/zh/', '/zh', '/', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/zh/foobar', '/zh', '/foobar', $cnDomainId, $cnUkId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://saleschannel.test', '', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/', '', '/', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/foobar', '', '/foobar', $ukDomainId, $cnUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'inactive' => [
            [
                self::getInactiveSalesChannel($chineseId, $cnDomainId, 'http://inactive.test'),
            ],
            [
                new ExpectedRequest('http://inactive.test', null, null, null, null, null, null, null, null, null, SalesChannelMappingException::class),
                new ExpectedRequest('http://inactive.test/', null, null, null, null, null, null, null, null, null, SalesChannelMappingException::class),
                new ExpectedRequest('http://inactive.test/foobar', null, null, null, null, null, null, null, null, null, SalesChannelMappingException::class),
            ],
        ];
        yield 'punycode' => [
            [
                self::getChineseSalesChannel($chineseId, $cnDomainId, 'http://中文.test'),
                self::getEnglishSalesChannel($englishId, $ukDomainId, 'http://xn--shpwre-eua5l.test'),
            ],
            [
                new ExpectedRequest('http://xn--fiq228c.test', '', '/', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://xn--fiq228c.test/', '', '/', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://xn--fiq228c.test/foobar', '', '/foobar', $cnDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, Defaults::CURRENCY, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://xn--shpwre-eua5l.test', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://xn--shpwre-eua5l.test/', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://xn--shpwre-eua5l.test/foobar', '', '/foobar', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
    }

    #[DataProvider('seoRedirectProvider')]
    public function testRedirectLinksUsesSalesChannelPath(string $baseUrl, string $virtualUrl, string $resolvedUrl): void
    {
        $cnUkId = Uuid::randomHex();

        $cnDomainId = Uuid::randomHex();
        $ukDomainId = Uuid::randomHex();

        $salesChannels = $this->getSalesChannelWithCnAndUkDomain($cnUkId, $cnDomainId, 'http://base.test' . $virtualUrl, $ukDomainId, 'http://base.test/public/en');

        $this->createSalesChannels([$salesChannels]);

        $con = static::getContainer()->get(Connection::class);
        $con->insert(
            'seo_url',
            [
                'id' => Uuid::randomBytes(),
                'language_id' => Uuid::fromHexToBytes($this->zhLanguageId),
                'sales_channel_id' => Uuid::fromHexToBytes($cnUkId),
                'foreign_key' => Uuid::randomBytes(),
                'route_name' => 'test',
                'path_info' => '/detail/87a78cf58f114d5587ae23c140825694',
                'seo_path_info' => 'Test',
                'is_canonical' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $request = Request::create('http://base.test' . $virtualUrl . '/detail/87a78cf58f114d5587ae23c140825694');
        $ref = new \ReflectionClass($request);
        $ref->getProperty('baseUrl')->setValue($request, $baseUrl);

        $resolved = $this->requestTransformer->transform($request);

        static::assertSame('http://base.test' . $resolvedUrl, $resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK));
    }

    public function testCanonicalSeoUrlWithQueryParameterDoesNotSetCanonicalLink(): void
    {
        $salesChannelId = Uuid::randomHex();
        $domainId = Uuid::randomHex();

        $this->createSalesChannels([
            self::getChineseSalesChannel($salesChannelId, $domainId, 'http://base.test'),
        ]);

        $con = static::getContainer()->get(Connection::class);
        $con->insert(
            'seo_url',
            [
                'id' => Uuid::randomBytes(),
                'language_id' => Uuid::fromHexToBytes($this->zhLanguageId),
                'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                'foreign_key' => Uuid::randomBytes(),
                'route_name' => ProductPageSeoUrlRoute::ROUTE_NAME,
                'path_info' => '/detail/87a78cf58f114d5587ae23c140825694',
                'seo_path_info' => 'Main-product/SWDEMO10001?test=123',
                'is_canonical' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $request = Request::create('http://base.test/Main-product/SWDEMO10001?test=123');

        $resolved = $this->requestTransformer->transform($request);

        static::assertSame(
            '/detail/87a78cf58f114d5587ae23c140825694',
            $resolved->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI)
        );

        // Matching canonical SEO URL should not set a canonical link (no redirect loop)
        static::assertNull($resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK));
    }

    public function testPlainCanonicalSeoUrlWithRequestQueryParameterDoesNotSetCanonicalLink(): void
    {
        $salesChannelId = Uuid::randomHex();
        $domainId = Uuid::randomHex();

        $this->createSalesChannels([
            self::getChineseSalesChannel($salesChannelId, $domainId, 'http://base.test'),
        ]);

        $con = static::getContainer()->get(Connection::class);
        $con->insert(
            'seo_url',
            [
                'id' => Uuid::randomBytes(),
                'language_id' => Uuid::fromHexToBytes($this->zhLanguageId),
                'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                'foreign_key' => Uuid::randomBytes(),
                'route_name' => ProductPageSeoUrlRoute::ROUTE_NAME,
                'path_info' => '/detail/87a78cf58f114d5587ae23c140825694',
                'seo_path_info' => 'Main-product/SWDEMO10001',
                'is_canonical' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $request = Request::create('http://base.test/Main-product/SWDEMO10001?utm=123');

        $resolved = $this->requestTransformer->transform($request);

        static::assertSame(
            '/detail/87a78cf58f114d5587ae23c140825694',
            $resolved->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI)
        );
        static::assertNull($resolved->attributes->get(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK));
    }

    /**
     * @return iterable<string, string[]>
     */
    public static function seoRedirectProvider(): iterable
    {
        yield 'Use with base url' => [
            '/public', // baseUrl
            '/public/zh', // Virtual URL
            '/public/zh/Test', // Resolved seo url
        ];

        yield 'Use with base url in subfolder' => [
            '/sw6/public', // baseUrl
            '/sw6/public/zh', // Virtual URL
            '/sw6/public/zh/Test', // Resolved seo url
        ];

        yield 'With Virtual url' => [
            '', // baseUrl
            '/zh', // Virtual URL
            '/zh/Test', // Resolved seo url
        ];

        yield 'Without virtual URL' => [
            '', // baseUrl
            '', // Virtual URL
            '/Test', // Resolved seo url
        ];
    }

    /**
     * @return SalesChannel
     */
    private static function getEnglishSalesChannel(string $salesChannelId, string $domainId, string $url): array
    {
        return [
            'id' => $salesChannelId,
            'name' => 'english',
            'active' => true,
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => self::LOCALE_EN_GB_ISO,
                ],
            ],
        ];
    }

    /**
     * @return SalesChannel
     */
    private static function getChineseSalesChannel(string $salesChannelId, string $domainId, string $url): array
    {
        return [
            'id' => $salesChannelId,
            'name' => 'chinese',
            'active' => true,
            'languages' => [
                ['id' => 'zh-CN'],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => 'zh-CN',
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => self::LOCALE_ZH_CN_ISO,
                ],
            ],
        ];
    }

    /**
     * @return SalesChannel
     */
    private static function getSalesChannelWithCnAndUkDomain(
        string $salesChannelId,
        string $cnDomainId,
        string $cnUrl,
        string $ukDomainId,
        string $ukUrl
    ): array {
        return [
            'id' => $salesChannelId,
            'name' => 'english',
            'active' => true,
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => self::LOCALE_ZH_CN_ISO],
            ],
            'domains' => [
                [
                    'id' => $cnDomainId,
                    'url' => $cnUrl,
                    'languageId' => self::LOCALE_ZH_CN_ISO,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => self::LOCALE_ZH_CN_ISO,
                ],
                [
                    'id' => $ukDomainId,
                    'url' => $ukUrl,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => self::LOCALE_EN_GB_ISO,
                ],
            ],
        ];
    }

    /**
     * @return SalesChannel
     */
    private static function getInactiveSalesChannel(string $salesChannelId, string $domainId, string $url): array
    {
        return [
            'id' => $salesChannelId,
            'name' => 'inactive sales channel',
            'active' => false,
            'languages' => [
                ['id' => self::LOCALE_ZH_CN_ISO],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => self::LOCALE_ZH_CN_ISO,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => self::LOCALE_ZH_CN_ISO,
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $salesChannels
     */
    private function createSalesChannels(array $salesChannels): EntityWrittenContainerEvent
    {
        $snippetSetEN = $this->getSnippetSetIdForLocale(self::LOCALE_EN_GB_ISO);
        $snippetSetZH = $this->getSnippetSetIdForLocale(self::LOCALE_ZH_CN_ISO);

        $salesChannels = array_map(function ($salesChannelData) use ($snippetSetZH, $snippetSetEN) {
            $defaults = [
                'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $snippetSetEN,
                'currencyId' => Defaults::CURRENCY,
                'currencyVersionId' => Defaults::LIVE_VERSION,
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'paymentMethodVersionId' => Defaults::LIVE_VERSION,
                'shippingMethodId' => $this->getValidShippingMethodId(),
                'shippingMethodVersionId' => Defaults::LIVE_VERSION,
                'navigationCategoryId' => $this->getValidCategoryId(),
                'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
                'countryId' => $this->getValidCountryId(),
                'countryVersionId' => Defaults::LIVE_VERSION,
                'currencies' => [['id' => Defaults::CURRENCY]],
                'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
                'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
                'countries' => [['id' => $this->getValidCountryId()]],
                'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            ];

            foreach ($salesChannelData['languages'] as &$language) {
                if ($language['id'] === self::LOCALE_ZH_CN_ISO) {
                    $language['id'] = $this->zhLanguageId;
                }

                if ($language['id'] === self::LOCALE_EN_GB_ISO) {
                    $language['id'] = Defaults::LANGUAGE_SYSTEM;
                }
            }

            foreach ($salesChannelData['domains'] as &$domain) {
                if ($domain['languageId'] === self::LOCALE_ZH_CN_ISO) {
                    $domain['languageId'] = $this->zhLanguageId;
                }

                if ($domain['languageId'] === self::LOCALE_EN_GB_ISO) {
                    $domain['languageId'] = Defaults::LANGUAGE_SYSTEM;
                }

                if ($domain['snippetSetId'] === self::LOCALE_EN_GB_ISO) {
                    $domain['snippetSetId'] = $snippetSetEN;
                }

                if ($domain['snippetSetId'] === self::LOCALE_ZH_CN_ISO) {
                    $domain['snippetSetId'] = $snippetSetZH;
                }
            }

            return array_merge_recursive($defaults, $salesChannelData);
        }, $salesChannels);

        return static::getContainer()->get('sales_channel.repository')->create($salesChannels, Context::createDefaultContext());
    }
}

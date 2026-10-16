<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Seo\AbstractSeoResolver;
use Shopwell\Core\Content\Seo\ResolvedSeoUrl;
use Shopwell\Core\Content\Seo\SeoUrlRequestContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Routing\RequestTransformerInterface;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\SalesChannelRequest;
use Shopwell\Storefront\Framework\Routing\AbstractDomainLoader;
use Shopwell\Storefront\Framework\Routing\Exception\SalesChannelMappingException;
use Shopwell\Storefront\Framework\Routing\RequestTransformer;
use Shopwell\Storefront\Framework\Routing\Struct\DomainCollection;
use Shopwell\Storefront\Framework\Routing\Struct\DomainStruct;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RequestTransformer::class)]
class RequestTransformerTest extends TestCase
{
    /**
     * @param list<string> $registeredApiPrefixes
     */
    #[DataProvider('notRequiredSalesChannelProvider')]
    public function testSalesChannelIsNotRequired(array $registeredApiPrefixes, string $requestUri): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = static::createStub(AbstractSeoResolver::class);
        $domainLoader = $this->createMock(AbstractDomainLoader::class);

        // should not be called as the sales channel is not required
        $domainLoader->expects($this->never())->method('loadDomains');

        $requestTransformer = new RequestTransformer($decorated, $resolver, $registeredApiPrefixes, $domainLoader);

        $originalRequest = Request::create($requestUri);
        $transformedRequest = $requestTransformer->transform($originalRequest);

        static::assertSame($originalRequest, $transformedRequest);
    }

    public function testSalesChannelIsRequired(): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = static::createStub(AbstractSeoResolver::class);
        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader->expects($this->once())->method('loadDomains')->willReturn(new DomainCollection());

        // no registered api prefixes ==> sales channel is always required
        $registeredApiPrefixes = [];
        $requestTransformer = new RequestTransformer($decorated, $resolver, $registeredApiPrefixes, $domainLoader);

        $originalRequest = Request::create('http://shopwell.cn/api');

        static::expectException(SalesChannelMappingException::class);
        $requestTransformer->transform($originalRequest);
    }

    /**
     * @param array<string, string> $serverVars
     */
    #[DataProvider('apiContextRouteBelowVirtualBaseUrlProvider')]
    public function testApiContextRouteBelowVirtualBaseUrlIsNotTransformed(string $requestUri, string $domainUrl, array $serverVars): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = static::createStub(AbstractSeoResolver::class);
        $resolver->method('resolveUrl')->willReturnCallback(static fn (SeoUrlRequestContext $context) => new ResolvedSeoUrl(
            pathInfo: '/' . ltrim($context->pathInfo, '/'),
            isCanonical: false,
        ));

        $requestTransformer = new RequestTransformer(
            $decorated,
            $resolver,
            ['admin', ApiRouteScope::ID, 'store-api'],
            $this->createDomainLoader($domainUrl),
            ['admin', ApiRouteScope::ID],
        );

        $originalRequest = Request::create($requestUri, 'GET', [], [], [], $serverVars);
        $transformedRequest = $requestTransformer->transform($originalRequest);

        static::assertSame($originalRequest, $transformedRequest);
        static::assertFalse($transformedRequest->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST));
        static::assertFalse($transformedRequest->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));
    }

    /**
     * @return iterable<string, array{requestUri: string, domainUrl: string, serverVars: array<string, string>}>
     */
    public static function apiContextRouteBelowVirtualBaseUrlProvider(): iterable
    {
        yield 'administration below a language path' => [
            'requestUri' => 'http://shopwell.cn/de/admin',
            'domainUrl' => 'http://shopwell.cn/de/',
            'serverVars' => [],
        ];

        yield 'administration below a language path with trailing slash' => [
            'requestUri' => 'http://shopwell.cn/de/admin/',
            'domainUrl' => 'http://shopwell.cn/de/',
            'serverVars' => [],
        ];

        yield 'admin api below a language path' => [
            'requestUri' => 'http://shopwell.cn/de/api/_info/version',
            'domainUrl' => 'http://shopwell.cn/de/',
            'serverVars' => [],
        ];

        yield 'administration below a language path of an installation in a subdirectory' => [
            'requestUri' => 'http://shopwell.cn/public/de/admin',
            'domainUrl' => 'http://shopwell.cn/public/de/',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/public/index.php',
                'PHP_SELF' => '/public/index.php',
            ],
        ];
    }

    public function testSeoUrlNamedLikeAnApiContextRouteBelowVirtualBaseUrlIsStillResolved(): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $categoryId = Uuid::randomHex();
        $resolver = static::createStub(AbstractSeoResolver::class);
        $resolver->method('resolveUrl')->willReturnCallback(static fn (SeoUrlRequestContext $context) => ltrim($context->pathInfo, '/') === 'admin'
            ? new ResolvedSeoUrl(pathInfo: '/navigation/' . $categoryId, isCanonical: true, id: Uuid::randomHex())
            : new ResolvedSeoUrl(pathInfo: '/' . ltrim($context->pathInfo, '/'), isCanonical: false));

        $requestTransformer = new RequestTransformer(
            $decorated,
            $resolver,
            ['admin', ApiRouteScope::ID, 'store-api'],
            $this->createDomainLoader('http://shopwell.cn/de/'),
            ['admin', ApiRouteScope::ID],
        );

        $transformedRequest = $requestTransformer->transform(Request::create('http://shopwell.cn/de/admin'));

        static::assertTrue($transformedRequest->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST));
        static::assertSame('/navigation/' . $categoryId, $transformedRequest->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI));
    }

    public function testStoreApiBelowVirtualBaseUrlIsStillTransformed(): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = static::createStub(AbstractSeoResolver::class);
        $resolver->method('resolveUrl')->willReturnCallback(static fn (SeoUrlRequestContext $context) => new ResolvedSeoUrl(
            pathInfo: '/' . ltrim($context->pathInfo, '/'),
            isCanonical: false,
        ));

        $requestTransformer = new RequestTransformer(
            $decorated,
            $resolver,
            ['admin', ApiRouteScope::ID, 'store-api'],
            $this->createDomainLoader('http://shopwell.cn/de/'),
            ['admin', ApiRouteScope::ID],
        );

        $transformedRequest = $requestTransformer->transform(Request::create('http://shopwell.cn/de/store-api/context'));

        static::assertTrue($transformedRequest->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST));
        static::assertSame('/store-api/context', $transformedRequest->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI));
    }

    public function testResolverReceivesQueryStringForExactMatching(): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(fn ($request) => $request);

        $languageId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $resolver = $this->createMock(AbstractSeoResolver::class);
        $resolver
            ->expects($this->once())
            ->method('resolveUrl')
            ->with(static::callback(static function (SeoUrlRequestContext $context) use ($languageId, $salesChannelId): bool {
                return $context->languageId === $languageId
                    && $context->salesChannelId === $salesChannelId
                    && $context->pathInfo === 'Main-product/SWDEMO10001'
                    && $context->queryString === 'test=123';
            }))
            ->willReturn(new ResolvedSeoUrl(pathInfo: '/detail/123', isCanonical: true));

        $domains = new DomainCollection();
        $domains->set('http://shopwell.cn/', DomainStruct::fromArray([
            'url' => 'http://shopwell.cn',
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannelId,
            'typeId' => Uuid::randomHex(),
            'snippetSetId' => Uuid::randomHex(),
            'currencyId' => Uuid::randomHex(),
            'languageId' => $languageId,
            'themeId' => Uuid::randomHex(),
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'en-GB',
            'themeName' => 'Storefront',
            'parentThemeName' => '',
        ]));

        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader
            ->expects($this->once())
            ->method('loadDomains')
            ->willReturn($domains);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [], $domainLoader);

        $originalRequest = Request::create('http://shopwell.cn/Main-product/SWDEMO10001?test=123');
        $transformedRequest = $requestTransformer->transform($originalRequest);

        static::assertSame('/detail/123', $transformedRequest->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI));
    }

    public function testResolverReceivesRawFlagQueryString(): void
    {
        // Symfony's Request::getQueryString() normalizes value-less keys: `?test123` becomes
        // `test123=`. The SEO URL resolver compares the request query against stored
        // seo_path_info verbatim, so it needs the raw form to match a stored `path?test123`.
        // The RequestTransformer reads QUERY_STRING from server vars rather than
        // getQueryString() to preserve that raw shape.
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(fn ($request) => $request);

        $languageId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $capturedContext = null;
        $resolver = $this->createMock(AbstractSeoResolver::class);
        $resolver
            ->expects($this->once())
            ->method('resolveUrl')
            ->willReturnCallback(static function (SeoUrlRequestContext $context) use (&$capturedContext): ResolvedSeoUrl {
                $capturedContext = $context;

                return new ResolvedSeoUrl(pathInfo: '/detail/123', isCanonical: true);
            });

        $domains = new DomainCollection();
        $domains->set('http://shopwell.cn/', DomainStruct::fromArray([
            'url' => 'http://shopwell.cn',
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannelId,
            'typeId' => Uuid::randomHex(),
            'snippetSetId' => Uuid::randomHex(),
            'currencyId' => Uuid::randomHex(),
            'languageId' => $languageId,
            'themeId' => Uuid::randomHex(),
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'en-GB',
            'themeName' => 'Storefront',
            'parentThemeName' => '',
        ]));

        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader
            ->expects($this->once())
            ->method('loadDomains')
            ->willReturn($domains);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [], $domainLoader);

        $originalRequest = Request::create('http://shopwell.cn/Latest-Product/SW10005?test12345');
        $requestTransformer->transform($originalRequest);

        static::assertNotNull($capturedContext);
        static::assertSame('test12345', $capturedContext->queryString, 'raw QUERY_STRING preserved (not normalized to "test12345=")');
        static::assertSame('Latest-Product/SW10005', $capturedContext->pathInfo);
    }

    public function testResolverReceivesNullForEmptyQueryString(): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(fn ($request) => $request);

        $languageId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $capturedContext = null;
        $resolver = $this->createMock(AbstractSeoResolver::class);
        $resolver
            ->expects($this->once())
            ->method('resolveUrl')
            ->willReturnCallback(static function (SeoUrlRequestContext $context) use (&$capturedContext): ResolvedSeoUrl {
                $capturedContext = $context;

                return new ResolvedSeoUrl(pathInfo: '/foo', isCanonical: false);
            });

        $domains = new DomainCollection();
        $domains->set('http://shopwell.cn/', DomainStruct::fromArray([
            'url' => 'http://shopwell.cn',
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannelId,
            'typeId' => Uuid::randomHex(),
            'snippetSetId' => Uuid::randomHex(),
            'currencyId' => Uuid::randomHex(),
            'languageId' => $languageId,
            'themeId' => Uuid::randomHex(),
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'en-GB',
            'themeName' => 'Storefront',
            'parentThemeName' => '',
        ]));

        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader
            ->expects($this->once())
            ->method('loadDomains')
            ->willReturn($domains);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [], $domainLoader);

        $originalRequest = Request::create('http://shopwell.cn/foo');
        $requestTransformer->transform($originalRequest);

        static::assertNotNull($capturedContext);
        static::assertNull($capturedContext->queryString);
    }

    /**
     * @param array<string, string> $serverVars
     */
    #[DataProvider('transformRequestProvider')]
    public function testTransformUsesBasePathInsteadOfBaseUrl(
        string $requestUrl,
        array $serverVars,
        string $domainUrl,
        string $expectedBaseUrl,
        string $expectedAbsoluteBaseUrl,
        string $expectedStorefrontUrl,
        string $expectedResolvedUri,
    ): void {
        $domainId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $themeId = Uuid::randomHex();

        $domainKey = rtrim($domainUrl, '/') . '/';

        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = static::createStub(AbstractSeoResolver::class);
        $resolver->method('resolveUrl')->willReturnCallback(static fn (SeoUrlRequestContext $context) => new ResolvedSeoUrl(
            pathInfo: '/' . ltrim($context->pathInfo, '/'),
            isCanonical: false,
        ));

        $domains = new DomainCollection();
        $domains->set($domainKey, DomainStruct::fromArray([
            'url' => $domainKey,
            'id' => $domainId,
            'salesChannelId' => $salesChannelId,
            'typeId' => 'storefront',
            'snippetSetId' => $snippetSetId,
            'currencyId' => $currencyId,
            'languageId' => $languageId,
            'themeId' => $themeId,
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'en-GB',
            'themeName' => 'Storefront',
            'parentThemeName' => '',
        ]));

        $domainLoader = static::createStub(AbstractDomainLoader::class);
        $domainLoader->method('loadDomains')->willReturn($domains);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [ApiRouteScope::ID], $domainLoader);

        $request = Request::create($requestUrl, 'GET', [], [], [], $serverVars);
        $transformed = $requestTransformer->transform($request);

        static::assertSame($expectedBaseUrl, $transformed->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL));
        static::assertSame($expectedAbsoluteBaseUrl, $transformed->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL));
        static::assertSame($expectedStorefrontUrl, $transformed->attributes->get(RequestTransformer::STOREFRONT_URL));
        static::assertSame($expectedResolvedUri, $transformed->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI));
        static::assertSame($salesChannelId, $transformed->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));
        static::assertTrue($transformed->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST));
    }

    /**
     * @return iterable<string, array{requestUrl: string, serverVars: array<string, string>, domainUrl: string, expectedBaseUrl: string, expectedAbsoluteBaseUrl: string, expectedStorefrontUrl: string, expectedResolvedUri: string}>
     */
    public static function transformRequestProvider(): iterable
    {
        yield 'index.php at root' => [
            'requestUrl' => 'http://shopwell.cn/index.php',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/index.php',
            ],
            'domainUrl' => 'http://shopwell.cn',
            'expectedBaseUrl' => '',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn',
            'expectedStorefrontUrl' => 'http://shopwell.cn',
            'expectedResolvedUri' => '/',
        ];

        yield 'index.php with virtual path and page' => [
            'requestUrl' => 'http://shopwell.cn/index.php/de/outdoor',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/index.php/de/outdoor',
            ],
            'domainUrl' => 'http://shopwell.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn',
            'expectedStorefrontUrl' => 'http://shopwell.cn/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'index.php in subdirectory' => [
            'requestUrl' => 'http://shopwell.cn/public/index.php/de',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/public/index.php',
                'PHP_SELF' => '/public/index.php/de',
            ],
            'domainUrl' => 'http://shopwell.cn/public/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn/public',
            'expectedStorefrontUrl' => 'http://shopwell.cn/public/de',
            'expectedResolvedUri' => '/',
        ];

        yield 'normal request without index.php' => [
            'requestUrl' => 'http://shopwell.cn/de/outdoor',
            'serverVars' => [],
            'domainUrl' => 'http://shopwell.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn',
            'expectedStorefrontUrl' => 'http://shopwell.cn/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'punycode to punycode direct hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com',
            'serverVars' => [],
            'domainUrl' => 'http://xn--shpwre-eua5l.com',
            'expectedBaseUrl' => '',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedStorefrontUrl' => 'http://shöpwäre.com',
            'expectedResolvedUri' => '/',
        ];

        yield 'punycode to unicode direct hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com',
            'serverVars' => [],
            'domainUrl' => 'http://shöpwäre.com',
            'expectedBaseUrl' => '',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedStorefrontUrl' => 'http://shöpwäre.com',
            'expectedResolvedUri' => '/',
        ];

        yield 'punycode to punycode filter hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com/de/outdoor',
            'serverVars' => [],
            'domainUrl' => 'http://xn--shpwre-eua5l.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedStorefrontUrl' => 'http://shöpwäre.com/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'punycode to unicode filter hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com/de/outdoor',
            'serverVars' => [],
            'domainUrl' => 'http://shöpwäre.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedStorefrontUrl' => 'http://shöpwäre.com/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'virtual path before index.php' => [
            // see https://github.com/shopwell-shop/shopwell/issues/6666
            'requestUrl' => 'http://shopwell.cn/de/index.php/navigation/abc',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php/navigation/abc',
            ],
            'domainUrl' => 'http://shopwell.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn',
            'expectedStorefrontUrl' => 'http://shopwell.cn/de',
            'expectedResolvedUri' => '/navigation/abc',
        ];

        yield 'virtual path before index.php in subdirectory' => [
            // see https://github.com/shopwell-shop/shopwell/issues/6666
            'requestUrl' => 'http://shopwell.cn/public/de/index.php/navigation/abc',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/public/index.php',
                'PHP_SELF' => '/public/de/index.php/navigation/abc',
            ],
            'domainUrl' => 'http://shopwell.cn/public/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn/public',
            'expectedStorefrontUrl' => 'http://shopwell.cn/public/de',
            'expectedResolvedUri' => '/navigation/abc',
        ];

        yield 'virtual path equals base url with index.php' => [
            // /de/index.php with no further path should resolve to the sales-channel home
            'requestUrl' => 'http://shopwell.cn/de/index.php',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php',
            ],
            'domainUrl' => 'http://shopwell.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn',
            'expectedStorefrontUrl' => 'http://shopwell.cn/de',
            'expectedResolvedUri' => '/',
        ];

        yield 'virtual path with trailing slash after index.php' => [
            // /de/index.php/ (trailing slash, no further path) should also resolve to home
            'requestUrl' => 'http://shopwell.cn/de/index.php/',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php/',
            ],
            'domainUrl' => 'http://shopwell.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn',
            'expectedStorefrontUrl' => 'http://shopwell.cn/de',
            'expectedResolvedUri' => '/',
        ];

        yield 'virtual path before custom front controller (app.php)' => [
            // ensure the strip uses basename($scriptName) and works for non-index.php front controllers
            'requestUrl' => 'http://shopwell.cn/de/app.php/navigation/abc',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/app.php',
                'SCRIPT_NAME' => '/app.php',
                'PHP_SELF' => '/de/app.php/navigation/abc',
            ],
            'domainUrl' => 'http://shopwell.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn',
            'expectedStorefrontUrl' => 'http://shopwell.cn/de',
            'expectedResolvedUri' => '/navigation/abc',
        ];

        yield 'slug with index.php prefix is preserved (boundary guard)' => [
            // a hypothetical SEO slug like "index.php-shop" must not be mangled by the strip;
            // the `$scriptName . '/'` suffix on str_starts_with ensures only the bare script
            // basename followed by a path separator is stripped.
            'requestUrl' => 'http://shopwell.cn/de/index.php-shop',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php-shop',
            ],
            'domainUrl' => 'http://shopwell.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn',
            'expectedStorefrontUrl' => 'http://shopwell.cn/de',
            'expectedResolvedUri' => '/index.php-shop',
        ];

        yield 'slug with index.php prefix is preserved when followed by sub-path' => [
            // an "index.php-shop" parent slug with a deeper path must also pass through unchanged.
            // The `$scriptName . '/'` boundary requires an exact script-name segment, so
            // "index.php-shop/foo" cannot be partial-stripped to "-shop/foo".
            'requestUrl' => 'http://shopwell.cn/de/index.php-shop/foo',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php-shop/foo',
            ],
            'domainUrl' => 'http://shopwell.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopwell.cn',
            'expectedStorefrontUrl' => 'http://shopwell.cn/de',
            'expectedResolvedUri' => '/index.php-shop/foo',
        ];
    }

    /**
     * @param array<string, string> $expectedQuery
     */
    #[DataProvider('resolvedQueryProvider')]
    public function testTransformMergesTheQueryOfTheResolvedPathInfo(
        string $requestUri,
        string $resolvedPathInfo,
        array $expectedQuery,
    ): void {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = static::createStub(AbstractSeoResolver::class);
        $resolver->method('resolveUrl')->willReturn(new ResolvedSeoUrl(pathInfo: $resolvedPathInfo, isCanonical: true));

        $domains = new DomainCollection();
        $domains->set('http://shopwell.cn/', DomainStruct::fromArray([
            'url' => 'http://shopwell.cn',
            'id' => Uuid::randomHex(),
            'salesChannelId' => Uuid::randomHex(),
            'typeId' => Uuid::randomHex(),
            'snippetSetId' => Uuid::randomHex(),
            'currencyId' => Uuid::randomHex(),
            'languageId' => Uuid::randomHex(),
            'themeId' => Uuid::randomHex(),
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'en-GB',
            'themeName' => 'Storefront',
            'parentThemeName' => '',
        ]));

        $domainLoader = static::createStub(AbstractDomainLoader::class);
        $domainLoader->method('loadDomains')->willReturn($domains);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [], $domainLoader);

        $transformedRequest = $requestTransformer->transform(Request::create('http://shopwell.cn' . $requestUri));

        static::assertSame($expectedQuery, $transformedRequest->query->all());
        static::assertSame(parse_url($resolvedPathInfo, \PHP_URL_PATH), $transformedRequest->getPathInfo());
    }

    /**
     * @return iterable<string, array{requestUri: string, resolvedPathInfo: string, expectedQuery: array<string, string>}>
     */
    public static function resolvedQueryProvider(): iterable
    {
        yield 'parameters of the resolved path info reach the query bag' => [
            'requestUri' => '/my-render-page',
            'resolvedPathInfo' => '/storefront/script/render?product-id=p1',
            'expectedQuery' => ['product-id' => 'p1'],
        ];

        yield 'resolved parameters win over browser parameters of the same name' => [
            'requestUri' => '/my-render-page?product-id=from-browser&keep=me',
            'resolvedPathInfo' => '/storefront/script/render?product-id=p1',
            'expectedQuery' => ['product-id' => 'p1', 'keep' => 'me'],
        ];

        yield 'browser parameters survive a resolved path info without a query' => [
            'requestUri' => '/awesome-product?test=123',
            'resolvedPathInfo' => '/detail/1234',
            'expectedQuery' => ['test' => '123'],
        ];
    }

    /**
     * @return iterable<string, array{registeredApiPrefixes: list<string>, requestUri: string}>
     */
    public static function notRequiredSalesChannelProvider(): iterable
    {
        yield 'Default case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn/api',
        ];

        yield 'Case with trailing slash' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn/api/',
        ];

        yield 'Case with double leading slashes' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn//api',
        ];

        yield 'Case with double trailing slashes' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn/api//',
        ];

        yield 'Case with double leading and trailing slashes' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn//api//',
        ];

        // Allowedlist paths:
        yield '_wdt case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn/_wdt/',
        ];

        yield '_profiler case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn/_profiler/',
        ];

        yield '_error case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn/_error/',
        ];

        yield 'payment finalize-transaction case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn/payment/finalize-transaction/',
        ];

        yield 'installer case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn/installer',
        ];

        yield '_fragment case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopwell.cn/_fragment/',
        ];
    }

    private function createDomainLoader(string $domainUrl): AbstractDomainLoader
    {
        $domains = new DomainCollection();
        $domains->set($domainUrl, DomainStruct::fromArray([
            'url' => $domainUrl,
            'id' => Uuid::randomHex(),
            'salesChannelId' => Uuid::randomHex(),
            'typeId' => 'storefront',
            'snippetSetId' => Uuid::randomHex(),
            'currencyId' => Uuid::randomHex(),
            'languageId' => Uuid::randomHex(),
            'themeId' => Uuid::randomHex(),
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'de-DE',
            'themeName' => 'Storefront',
            'parentThemeName' => '',
        ]));

        $domainLoader = static::createStub(AbstractDomainLoader::class);
        $domainLoader->method('loadDomains')->willReturn($domains);

        return $domainLoader;
    }
}

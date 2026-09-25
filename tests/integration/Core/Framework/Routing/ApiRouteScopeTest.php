<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\ContextSource;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
class ApiRouteScopeTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @return list<array{ContextSource, bool}>
     */
    public static function provideAllowedData(): array
    {
        return [
            [new AdminApiSource(null, null), true],
            [new AdminApiSource(null, null), false],
            [new SystemSource(), false],
        ];
    }

    /**
     * @return list<array{ContextSource, bool}>
     */
    public static function provideForbiddenData(): array
    {
        return [
            [new SalesChannelApiSource(Uuid::randomHex()), true],
            [new SystemSource(), true],
        ];
    }

    #[DataProvider('provideAllowedData')]
    public function testAllowedCombinations(ContextSource $source, bool $authRequired): void
    {
        $scope = static::getContainer()->get(ApiRouteScope::class);

        $request = Request::create('/api/foo');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, Context::createDefaultContext($source));
        $request->attributes->set('auth_required', $authRequired);

        static::assertTrue($scope->isAllowedPath($request->getPathInfo()));
        static::assertTrue($scope->isAllowed($request));
    }

    #[DataProvider('provideForbiddenData')]
    public function testForbiddenCombinations(ContextSource $source, bool $authRequired): void
    {
        $scope = static::getContainer()->get(ApiRouteScope::class);

        $request = Request::create('/api/foo');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, Context::createDefaultContext($source));
        $request->attributes->set('auth_required', $authRequired);

        static::assertFalse($scope->isAllowed($request));
    }
}

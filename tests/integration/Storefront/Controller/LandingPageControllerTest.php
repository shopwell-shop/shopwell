<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Debugging\ScriptTraces;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Storefront\Page\LandingPage\LandingPageLoadedHook;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class LandingPageControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();
    }

    public function testLandingPageLoadedHookScriptsAreExecuted(): void
    {
        $this->assignContentLayout(salesChannelAssignment: false);

        $response = $this->request('GET', '/myUrl', []);

        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getStorefrontRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(LandingPageLoadedHook::HOOK_NAME, $traces);
    }

    #[DataProvider('contentLayoutProvider')]
    public function testRendersAssignedContentLayout(bool $salesChannelAssignment): void
    {
        $context = Context::createDefaultContext();
        $landingPage = [
            'id' => $this->ids->get('landing-page'),
            'name' => 'Assigned landing page',
            'metaTitle' => 'Landing campaign title',
        ];
        static::getContainer()->get('landing_page.repository')->update([$landingPage], $context);
        $this->assignContentLayout($salesChannelAssignment);

        $response = $this->request('GET', '/myUrl', []);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringContainsString('data-page-id="' . $this->ids->get('content-layout') . '"', $content);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('content-text') . '"', $content);
        static::assertStringContainsString('Assigned landing page', $content);
        static::assertStringContainsString('Landing campaign title', $content);

        $traces = $this->getStorefrontRequestContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(LandingPageLoadedHook::HOOK_NAME, $traces);
    }

    /**
     * @return \Generator<string, array{bool}>
     */
    public static function contentLayoutProvider(): \Generator
    {
        yield 'global assignment' => [false];
        yield 'sales channel assignment' => [true];
    }

    #[DataProvider('contentRouteParametersProvider')]
    public function testContentLandingPageIgnoresContentRouteParameters(string $parameter, mixed $value): void
    {
        $this->assignContentLayout(salesChannelAssignment: false);

        $response = $this->request('GET', '/myUrl', [$parameter => $value]);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('content-root') . '"', $content);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('content-text') . '"', $content);
    }

    /**
     * @return \Generator<string, array{string, string|array<string, string>}>
     */
    public static function contentRouteParametersProvider(): \Generator
    {
        yield 'scalar includes does not reject the page' => ['includes', '1'];
        yield 'array includes does not reject the page' => ['includes', ['x' => 'y']];
        yield 'array excludes does not reject the page' => ['excludes', ['x' => '1']];
        yield 'unknown element does not reject the page' => ['elementId', 'unknown'];
    }

    public function testContentLandingPageDoesNotRenderOnlyTheRequestedElement(): void
    {
        $this->assignContentLayout(salesChannelAssignment: false);

        $response = $this->request('GET', '/myUrl', ['elementId' => $this->ids->get('content-text')]);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('content-root') . '"', $content);
        static::assertStringContainsString('data-element-id="' . $this->ids->get('content-text') . '"', $content);
    }

    public function testBrokenContentLayoutIsNotFound(): void
    {
        $this->assignContentLayout(salesChannelAssignment: false);
        static::getContainer()->get(Connection::class)->update('content_layout', [
            'layout' => '[{}]',
        ], ['id' => Uuid::fromHexToBytes($this->ids->get('content-layout'))]);

        $response = $this->request('GET', '/myUrl', []);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), $content);
    }

    public function testLandingPageWithoutAnyLayoutIsNotFound(): void
    {
        $response = $this->request('GET', '/myUrl', []);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testAssignedLayoutDoesNotExposeInactiveLandingPage(): void
    {
        $this->assignContentLayout(salesChannelAssignment: false);
        static::getContainer()->get('landing_page.repository')->update([[
            'id' => $this->ids->get('landing-page'),
            'active' => false,
        ]], Context::createDefaultContext());

        $response = $this->request('GET', '/myUrl', []);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function assignContentLayout(bool $salesChannelAssignment): void
    {
        $context = Context::createDefaultContext();
        static::getContainer()->get('content_layout.repository')->create([[
            'id' => $this->ids->create('content-layout'),
            'name' => 'Landing campaign',
            'rootSource' => 'landing_page',
            'version' => '1.0.0',
            'layout' => [[
                'id' => $this->ids->create('content-root'),
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'slots' => ['content' => [[
                    'id' => $this->ids->create('content-text'),
                    'component' => 'Sw:Content:Text',
                    'properties' => ['text' => [Defaults::LANGUAGE_SYSTEM => '{{map:landing_page.name}}']],
                ]]],
            ]],
        ]], $context);
        static::getContainer()->get('landing_page_content_layout.repository')->create([[
            'id' => $this->ids->create('content-assignment'),
            'landingPageId' => $this->ids->get('landing-page'),
            'salesChannelId' => $salesChannelAssignment ? $this->getSalesChannelId() : null,
            'contentLayoutId' => $this->ids->get('content-layout'),
        ]], $context);
    }

    private function createData(): void
    {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = static::getContainer()->get('sales_channel.repository')->search(
            (
                new Criteria())->addFilter(
                    new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                    new EqualsFilter('domains.url', $_SERVER['APP_URL'])
                ),
            Context::createDefaultContext()
        )->getEntities()->first();

        $data = [
            'id' => $this->ids->create('landing-page'),
            'name' => 'Test',
            'url' => 'myUrl',
            'active' => true,
            'salesChannels' => [
                [
                    'id' => $salesChannel->getId(),
                ],
            ],
        ];

        static::getContainer()->get('landing_page.repository')
            ->create([$data], Context::createDefaultContext());
    }
}

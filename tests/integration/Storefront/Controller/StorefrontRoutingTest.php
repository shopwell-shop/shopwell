<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\Exception\InvalidRouteScopeException;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Storefront\Event\StorefrontRenderEvent;
use Shopwell\Storefront\Page\Navigation\NavigationPage;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class StorefrontRoutingTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;
    use SessionTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testForwardFromAddPromotionToHomePage(): void
    {
        $renderedParameters = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            StorefrontRenderEvent::class,
            static function (StorefrontRenderEvent $event) use (&$renderedParameters): void {
                $skippedViews = [
                    '@Storefront/storefront/layout/header.html.twig',
                    '@Storefront/storefront/layout/footer.html.twig',
                ];
                if (\in_array($event->getView(), $skippedViews, true)) {
                    return;
                }

                $renderedParameters[] = $event->getParameters();
            }
        );

        $response = $this->request(
            'POST',
            '/checkout/promotion/add',
            $this->tokenize('frontend.checkout.promotion.add', [
                'forwardTo' => 'frontend.home.page',
            ])
        );

        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $renderedParameters);
        $page = $renderedParameters[0]['page'];
        static::assertInstanceOf(NavigationPage::class, $page);
        static::assertInstanceOf(CmsPageEntity::class, $page->getCmsPage());
        static::assertSame('Default listing layout', $page->getCmsPage()->getName());
    }

    public function testForwardFromAddPromotionToApiFails(): void
    {
        $response = $this->request(
            'POST',
            '/checkout/promotion/add',
            $this->tokenize('frontend.checkout.promotion.add', [
                'forwardTo' => 'api.action.user.user-recovery.hash',
            ])
        );

        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertStringContainsString(InvalidRouteScopeException::class, $response->getContent());
    }
}

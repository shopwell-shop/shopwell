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
        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            StorefrontRenderEvent::class,
            static function (StorefrontRenderEvent $event): void {
                $skippedViews = [
                    '@Storefront/storefront/layout/header.html.twig',
                    '@Storefront/storefront/layout/footer.html.twig',
                ];
                if (\in_array($event->getView(), $skippedViews, true)) {
                    return;
                }

                $data = $event->getParameters();
                static::assertInstanceOf(NavigationPage::class, $data['page']);
                static::assertInstanceOf(CmsPageEntity::class, $data['page']->getCmsPage());
                static::assertSame('Default listing layout', $data['page']->getCmsPage()->getName());
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

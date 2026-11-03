<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\Membership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipRoute;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipRouteResponse;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopwell\Storefront\Page\Account\Membership\AccountMembershipPage;
use Shopwell\Storefront\Page\Account\Membership\AccountMembershipPageLoadedEvent;
use Shopwell\Storefront\Page\Account\Membership\AccountMembershipPageLoader;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountMembershipPageLoader::class)]
class AccountMembershipPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
    }

    public function testCarriesTheOfferAndTheRunningMembership(): void
    {
        $membership = [
            'plans' => [['id' => 'plan-year', 'name' => 'Yearly', 'price' => 299.0]],
            'channels' => [['channel' => 'wechat', 'label' => 'WeChat Pay', 'flow' => 'qrcode']],
            'orders' => [['id' => 'order-1', 'state' => 'activated']],
            'subscription' => [
                'id' => 'subscription-1',
                'planName' => 'Yearly',
                'endsAt' => '2027-03-01T00:00:00+00:00',
            ],
        ];

        $page = $this->load($membership, 'translated', 'testshop');

        static::assertSame($membership['plans'], $page->getMembership()->get('plans'));
        static::assertSame($membership['subscription'], $page->getMembership()->get('subscription'));

        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(AccountMembershipPageLoadedEvent::class, $events[0]);
        static::assertSame($page, $events[0]->getPage());
    }

    public function testACustomerWithoutAMembershipStillSeesThePlans(): void
    {
        $page = $this->load([
            'plans' => [['id' => 'plan-month', 'name' => 'Monthly', 'price' => 39.0]],
            'channels' => [],
            'orders' => [],
            'subscription' => null,
        ]);

        static::assertCount(1, $page->getMembership()->get('plans'));
        static::assertNull($page->getMembership()->get('subscription'));
    }

    /**
     * @param array<string, mixed> $membership
     */
    private function load(array $membership, string $translated = 'translated', string $shopTitle = 'testshop'): AccountMembershipPage
    {
        $genericPage = new Page();
        $genericPage->setMetaInformation(new MetaInformation());
        $genericPage->getMetaInformation()?->setMetaTitle($shopTitle);

        $genericLoader = $this->createMock(GenericPageLoaderInterface::class);
        $genericLoader->expects($this->once())->method('load')->willReturn($genericPage);

        $membershipRoute = $this->createMock(MembershipRoute::class);
        $membershipRoute->expects($this->once())
            ->method('load')
            ->willReturn(new MembershipRouteResponse(new ArrayStruct($membership)));

        $translator = static::createStub(AbstractTranslator::class);
        $translator->method('trans')->willReturn($translated);

        $loader = new AccountMembershipPageLoader(
            $genericLoader,
            $this->eventDispatcher,
            $membershipRoute,
            $translator
        );

        return $loader->load(new Request(), static::createStub(SalesChannelContext::class), new CustomerEntity());
    }
}

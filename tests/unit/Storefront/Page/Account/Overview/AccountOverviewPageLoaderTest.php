<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\Overview;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerRoute;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRoute;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRouteResponse;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyTierProgress;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletRouteResponse;
use Shopwell\Core\Checkout\Wallet\Service\WalletSettings;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopwell\Storefront\Checkout\Order\OrderProductAvailabilityResolver;
use Shopwell\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopwell\Storefront\Page\Account\Order\AccountOrderStateCounter;
use Shopwell\Storefront\Page\Account\Overview\AccountOverviewPage;
use Shopwell\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopwell\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopwell\Storefront\Page\GenericPageLoader;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Page\Page;
use Shopwell\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountOverviewPageLoader::class)]
class AccountOverviewPageLoaderTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    private const CURRENCY_ID = 'currency-cny';

    private CollectingEventDispatcher $eventDispatcher;

    private OrderRoute&Stub $orderRoute;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericPageLoader;

    private WalletRoute&Stub $walletRoute;

    private LoyaltyRoute&Stub $loyaltyRoute;

    /**
     * The wallet and the membership are configured through the system config, so the tests flip the real
     * settings instead of stubbing them away: "switched off" has to mean what `WalletSettings` and
     * `LoyaltySettings` say it means, not what a stub pretends.
     */
    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = static::createStub(OrderRoute::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericPageLoader = static::createStub(GenericPageLoader::class);
        $this->walletRoute = $this->walletRouteAnswering([]);
        $this->loyaltyRoute = $this->loyaltyRouteAnswering([]);
        $this->systemConfigService = new StaticSystemConfigService();
    }

    public function testLoad(): void
    {
        $order = (new OrderEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]);

        $orders = new OrderCollection([$order]);

        $orderResponse = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $orderRoute = $this->createMock(OrderRoute::class);
        // one call for the recent orders, one per KPI count - the counts only read the total
        $orderRoute
            ->expects($this->exactly(3))
            ->method('load')
            ->willReturn($orderResponse);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $genericPageLoader = $this->createMock(GenericPageLoader::class);
        $genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $pageLoader = $this->createPageLoader(
            genericPageLoader: $genericPageLoader,
            orderRoute: $orderRoute,
            translator: $translator,
        );

        $customer = new CustomerEntity();
        $page = $pageLoader->load(new Request(), $this->salesChannelContext(), $customer);

        static::assertSame($order, $page->getNewestOrder());
        static::assertCount(1, $page->getRecentOrders());
        static::assertSame($order, $page->getRecentOrders()->first());
        static::assertSame(1, $page->getPendingPaymentCount());
        static::assertSame(1, $page->getPendingDeliveryCount());

        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(4, $events);

        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[0]);
        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[1]);
        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[2]);
        static::assertInstanceOf(AccountOverviewPageLoadedEvent::class, $events[3]);
    }

    public function testSetStandardMetaData(): void
    {
        $pageLoader = new TestAccountOverviewPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->eventDispatcher,
            $this->orderRoute,
            static::createStub(CustomerRoute::class),
            static::createStub(NewsletterAccountPageletLoader::class),
            $this->translator,
            static::createStub(OrderProductAvailabilityResolver::class),
            $this->walletRoute,
            new WalletSettings($this->systemConfigService),
            $this->loyaltyRoute,
            new LoyaltySettings($this->systemConfigService),
            new LoyaltyTierProgress(),
            $this->orderStateCounter($this->orderRoute)
        );

        $page = new AccountOverviewPage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }

    public function testTheBalanceOfTheCurrencyTheCustomerShopsInIsTheOneShown(): void
    {
        $walletRoute = $this->walletRouteAnswering([
            ['id' => 'wallet-eur', 'currencyId' => 'currency-eur', 'balance' => 42.5],
            ['id' => 'wallet-cny', 'currencyId' => self::CURRENCY_ID, 'balance' => 1399.0],
        ]);

        $page = $this->loadOverview(walletRoute: $walletRoute);

        // the wallet route answers per currency; the dashboard prints one number, the current currency
        static::assertSame(1399.0, $page->getWalletBalance());
    }

    public function testAWalletWithoutAnEntryInTheCurrentCurrencyIsEmptyRatherThanMissing(): void
    {
        $walletRoute = $this->walletRouteAnswering([
            ['id' => 'wallet-eur', 'currencyId' => 'currency-eur', 'balance' => 42.5],
        ]);

        $page = $this->loadOverview(walletRoute: $walletRoute);

        // the feature is on, the balance simply holds nothing - that is 0.0, not null
        static::assertSame(0.0, $page->getWalletBalance());
    }

    public function testASwitchedOffWalletLeavesTheBalanceNullAndAsksNoRoute(): void
    {
        $walletRoute = $this->createMock(WalletRoute::class);
        $walletRoute->expects($this->never())->method('load');

        $this->systemConfigService->set(WalletSettings::CONFIG_KEY_ENABLED, false);

        $page = $this->loadOverview(walletRoute: $walletRoute);

        static::assertNull($page->getWalletBalance());
        // the rest of the page still loads - a switched-off wallet is not an error
        static::assertCount(0, $page->getRecentOrders());
    }

    public function testASwitchedOffMembershipLeavesPointsAndTierNullAndAsksNoRoute(): void
    {
        $loyaltyRoute = $this->createMock(LoyaltyRoute::class);
        $loyaltyRoute->expects($this->never())->method('load');

        $this->systemConfigService->set(LoyaltySettings::CONFIG_KEY_ENABLED, false);

        $page = $this->loadOverview(loyaltyRoute: $loyaltyRoute);

        static::assertNull($page->getRewards());
        static::assertNull($page->getCurrentTier());
        static::assertNull($page->getNextTier());
        static::assertNull($page->getTierPointsToNext());
        static::assertSame(0, $page->getTierPoints());
        static::assertSame(0, $page->getTierProgress());
    }

    public function testTheTierTheCustomerIsClimbingTowardsIsTheOneTheProgressBarShows(): void
    {
        $loyaltyRoute = $this->loyaltyRouteAnswering([
            'pointsBalance' => 650,
            'tierPoints' => 15000,
            'tierId' => 'tier-gold',
            'tiers' => [
                $this->tier('tier-silver', 'Silver', 0),
                $this->tier('tier-gold', 'Gold', 5000),
                $this->tier('tier-platinum', 'Platinum', 20000),
            ],
        ]);

        $page = $this->loadOverview(loyaltyRoute: $loyaltyRoute);

        static::assertNotNull($page->getRewards());
        static::assertSame(650, $page->getRewards()->get('pointsBalance'));
        static::assertSame('Gold', $page->getCurrentTier()['name'] ?? null);
        static::assertSame('Platinum', $page->getNextTier()['name'] ?? null);
        static::assertSame(15000, $page->getTierPoints());
        // 15000 of the 15000 tier points between 5000 and 20000
        static::assertSame(66, $page->getTierProgress());
        static::assertSame(5000, $page->getTierPointsToNext());
    }

    public function testTheHighestTierHasNoNextStepAndThereforeNoRemainingPoints(): void
    {
        $loyaltyRoute = $this->loyaltyRouteAnswering([
            'pointsBalance' => 650,
            'tierPoints' => 25000,
            'tierId' => 'tier-platinum',
            'tiers' => [
                $this->tier('tier-gold', 'Gold', 5000),
                $this->tier('tier-platinum', 'Platinum', 20000),
            ],
        ]);

        $page = $this->loadOverview(loyaltyRoute: $loyaltyRoute);

        static::assertSame('Platinum', $page->getCurrentTier()['name'] ?? null);
        static::assertNull($page->getNextTier());
        static::assertSame(0, $page->getTierProgress());
        static::assertNull($page->getTierPointsToNext());
    }

    private function loadOverview(
        ?WalletRoute $walletRoute = null,
        ?LoyaltyRoute $loyaltyRoute = null,
    ): AccountOverviewPage {
        $pageLoader = $this->createPageLoader(
            genericPageLoader: $this->genericPageLoader(),
            orderRoute: $this->orderRouteReturning(new OrderCollection(), 0),
            walletRoute: $walletRoute,
            loyaltyRoute: $loyaltyRoute,
        );

        return $pageLoader->load(new Request(), $this->salesChannelContext(), new CustomerEntity());
    }

    private function genericPageLoader(): GenericPageLoaderInterface
    {
        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $loader = static::createStub(GenericPageLoaderInterface::class);
        $loader->method('load')->willReturn($page);

        return $loader;
    }

    private function orderRouteReturning(OrderCollection $orders, int $total): AbstractOrderRoute
    {
        $response = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                $total,
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $route = static::createStub(AbstractOrderRoute::class);
        $route->method('load')->willReturn($response);

        return $route;
    }

    /**
     * @param list<array<string, mixed>> $wallets
     */
    private function walletRouteAnswering(array $wallets): WalletRoute&Stub
    {
        $route = static::createStub(WalletRoute::class);
        $route->method('load')->willReturn(new WalletRouteResponse(new ArrayStruct(['wallets' => $wallets])));

        return $route;
    }

    /**
     * @param array<string, mixed> $rewards
     */
    private function loyaltyRouteAnswering(array $rewards): LoyaltyRoute&Stub
    {
        $route = static::createStub(LoyaltyRoute::class);
        $route->method('load')->willReturn(new LoyaltyRouteResponse(new ArrayStruct($rewards)));

        return $route;
    }

    private function salesChannelContext(string $currencyId = self::CURRENCY_ID): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);
        $context->method('getCurrency')->willReturn((new CurrencyEntity())->assign(['id' => $currencyId]));

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function tier(string $id, string $name, int $minTierPoints): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'minTierPoints' => $minTierPoints,
            'benefits' => [],
        ];
    }

    private function createPageLoader(
        ?GenericPageLoaderInterface $genericPageLoader = null,
        ?AbstractOrderRoute $orderRoute = null,
        ?AbstractTranslator $translator = null,
        ?WalletRoute $walletRoute = null,
        ?LoyaltyRoute $loyaltyRoute = null,
    ): AccountOverviewPageLoader {
        return new AccountOverviewPageLoader(
            $genericPageLoader ?? $this->genericPageLoader,
            $this->eventDispatcher,
            $orderRoute ?? $this->orderRoute,
            static::createStub(CustomerRoute::class),
            static::createStub(NewsletterAccountPageletLoader::class),
            $translator ?? $this->translator,
            static::createStub(OrderProductAvailabilityResolver::class),
            $walletRoute ?? $this->walletRoute,
            new WalletSettings($this->systemConfigService),
            $loyaltyRoute ?? $this->loyaltyRoute,
            new LoyaltySettings($this->systemConfigService),
            new LoyaltyTierProgress(),
            $this->orderStateCounter($orderRoute ?? $this->orderRoute)
        );
    }

    private function orderStateCounter(AbstractOrderRoute $orderRoute): AccountOrderStateCounter
    {
        return new AccountOrderStateCounter($orderRoute, $this->eventDispatcher);
    }
}

/**
 * @internal
 */
class TestAccountOverviewPageLoader extends AccountOverviewPageLoader
{
    public function setMetaInformationAccess(AccountOverviewPage $page): void
    {
        self::setMetaInformation($page);
    }
}

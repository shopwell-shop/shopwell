<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\Rewards;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRoute;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRouteResponse;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyTierProgress;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopwell\Storefront\Page\Account\Rewards\AccountRewardsPage;
use Shopwell\Storefront\Page\Account\Rewards\AccountRewardsPageLoadedEvent;
use Shopwell\Storefront\Page\Account\Rewards\AccountRewardsPageLoader;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountRewardsPageLoader::class)]
class AccountRewardsPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
    }

    public function testCarriesTheRewardsAndPicksTheTierTheCustomerIsClimbingTowards(): void
    {
        $page = $this->load(
            [
                'pointsBalance' => 1280,
                'tierPoints' => 280,
                'tierId' => 'tier-gold',
                'tiers' => [
                    $this->tier('tier-silver', 'Silver', 0),
                    $this->tier('tier-gold', 'Gold', 200),
                    $this->tier('tier-platinum', 'Platinum', 1000),
                ],
            ],
            'translated',
            'testshop'
        );

        static::assertSame(1280, $page->getRewards()->get('pointsBalance'));
        static::assertSame('Gold', $page->getCurrentTier()['name'] ?? null);
        static::assertSame('Platinum', $page->getNextTier()['name'] ?? null);

        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(AccountRewardsPageLoadedEvent::class, $events[0]);
        static::assertSame($page, $events[0]->getPage());
    }

    public function testACustomerWithoutATierStartsAtTheFirstOne(): void
    {
        $page = $this->load([
            'pointsBalance' => 0,
            'tierPoints' => 0,
            'tierId' => null,
            'tiers' => [
                $this->tier('tier-silver', 'Silver', 0),
                $this->tier('tier-gold', 'Gold', 200),
            ],
        ]);

        static::assertNull($page->getCurrentTier());
        static::assertSame('Silver', $page->getNextTier()['name'] ?? null);
    }

    public function testTheHighestTierHasNothingLeftToClimb(): void
    {
        $page = $this->load([
            'pointsBalance' => 5000,
            'tierPoints' => 1500,
            'tierId' => 'tier-platinum',
            'tiers' => [
                $this->tier('tier-gold', 'Gold', 200),
                $this->tier('tier-platinum', 'Platinum', 1000),
            ],
        ]);

        static::assertSame('Platinum', $page->getCurrentTier()['name'] ?? null);
        static::assertNull($page->getNextTier());
    }

    public function testAShopWithoutTiersStillLoadsThePage(): void
    {
        $page = $this->load([
            'pointsBalance' => 10,
            'tierPoints' => 10,
            'tierId' => null,
            'tiers' => [],
        ]);

        static::assertNull($page->getCurrentTier());
        static::assertNull($page->getNextTier());
        static::assertSame(10, $page->getRewards()->get('pointsBalance'));
    }

    /**
     * @param array<string, mixed> $rewards
     */
    private function load(array $rewards, string $translated = 'translated', string $shopTitle = 'testshop'): AccountRewardsPage
    {
        $genericPage = new Page();
        $genericPage->setMetaInformation(new MetaInformation());
        $genericPage->getMetaInformation()?->setMetaTitle($shopTitle);

        $genericLoader = $this->createMock(GenericPageLoaderInterface::class);
        $genericLoader->expects($this->once())->method('load')->willReturn($genericPage);

        $loyaltyRoute = $this->createMock(LoyaltyRoute::class);
        $loyaltyRoute->expects($this->once())
            ->method('load')
            ->willReturn(new LoyaltyRouteResponse(new ArrayStruct($rewards)));

        $translator = static::createStub(AbstractTranslator::class);
        $translator->method('trans')->willReturn($translated);

        // the real projection, not a stub: what tier the page prints is exactly what this test is about,
        // and the projection is pure - no repository, no context, nothing to isolate
        $loader = new AccountRewardsPageLoader(
            $genericLoader,
            $this->eventDispatcher,
            $loyaltyRoute,
            $translator,
            new LoyaltyTierProgress()
        );

        return $loader->load(new Request(), static::createStub(SalesChannelContext::class), new CustomerEntity());
    }

    /**
     * The payload of a tier as the route sends it: what it grants is a keyed list of benefit rows, and
     * a tier that grants nothing sends an empty list rather than three neutral numbers.
     *
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
}

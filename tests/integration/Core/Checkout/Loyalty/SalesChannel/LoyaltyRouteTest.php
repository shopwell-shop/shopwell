<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Loyalty\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class LoyaltyRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testRequiresLogin(): void
    {
        $this->browser->request('GET', '/store-api/loyalty');

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
    }

    public function testReturnsZeroedLoyaltyForNewCustomer(): void
    {
        $this->login($this->browser);

        $this->browser->request('GET', '/store-api/loyalty');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertSame(0, $response['pointsBalance']);
        static::assertSame(0, $response['growthValue']);
        static::assertNull($response['membershipTierId']);
        static::assertSame([], $response['tiers']);
    }

    public function testReturnsLoyaltyDataAndActiveTiers(): void
    {
        $customerId = $this->login($this->browser);

        // the membership is opened with a zero balance when the customer is written, so the test
        // updates it instead of opening a second one that the unique key on the customer would reject
        $loyaltyRepository = static::getContainer()->get('customer_loyalty.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        $loyaltyId = $loyaltyRepository->searchIds($criteria, $context)->firstId();
        static::assertNotNull($loyaltyId);

        $loyaltyRepository->update([
            [
                'id' => $loyaltyId,
                'pointsBalance' => 250,
                'growthValue' => 100,
            ],
        ], $context);

        $tierId = Uuid::randomHex();
        static::getContainer()->get('loyalty_tier.repository')->create([
            [
                'id' => $tierId,
                'technicalName' => 'gold',
                'level' => 2,
                'minGrowthValue' => 50,
                'pointsMultiplier' => 1.5,
                'active' => true,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Gold',
                    ],
                ],
            ],
        ], $context);

        $this->browser->request('GET', '/store-api/loyalty');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertSame(250, $response['pointsBalance']);
        static::assertSame(100, $response['growthValue']);
        static::assertCount(1, $response['tiers']);
        static::assertSame($tierId, $response['tiers'][0]['id']);
        static::assertSame('gold', $response['tiers'][0]['technicalName']);
        static::assertSame(2, $response['tiers'][0]['level']);
    }
}

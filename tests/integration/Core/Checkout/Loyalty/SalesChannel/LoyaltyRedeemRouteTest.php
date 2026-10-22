<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Loyalty\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class LoyaltyRedeemRouteTest extends TestCase
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
        $this->browser->request('POST', '/store-api/loyalty/redeem-points', ['points' => 10]);

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
    }

    public function testRejectsNegativePoints(): void
    {
        $this->login($this->browser);

        $this->browser->request('POST', '/store-api/loyalty/redeem-points', ['points' => -1]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame(LoyaltyException::LOYALTY_INVALID_POINTS_CODE, $response['errors'][0]['code']);
    }

    public function testRejectsPointsBelowMinimum(): void
    {
        $this->login($this->browser);

        static::getContainer()->get(SystemConfigService::class)->set(LoyaltySettings::CONFIG_KEY_MIN_REDEEMABLE_POINTS, 10);

        $this->browser->request('POST', '/store-api/loyalty/redeem-points', ['points' => 5]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame(LoyaltyException::LOYALTY_BELOW_MINIMUM_POINTS_CODE, $response['errors'][0]['code']);
    }

    public function testRejectsPointsAboveBalance(): void
    {
        $customerId = $this->login($this->browser);

        static::getContainer()->get(SystemConfigService::class)->set(LoyaltySettings::CONFIG_KEY_MIN_REDEEMABLE_POINTS, 1);

        $this->setPoints($customerId, 10);

        $this->browser->request('POST', '/store-api/loyalty/redeem-points', ['points' => 50]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame(LoyaltyException::LOYALTY_INSUFFICIENT_POINTS_CODE, $response['errors'][0]['code']);
    }

    public function testZeroPointsClearsTheRedemption(): void
    {
        $this->login($this->browser);

        $this->browser->request('POST', '/store-api/loyalty/redeem-points', ['points' => 0]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('price', $response);
    }

    /**
     * The membership of a customer is opened with a zero balance when the customer is written. The
     * test books points onto it instead of opening a second membership, which the unique key on the
     * customer would reject.
     */
    private function setPoints(string $customerId, int $points): void
    {
        $repository = static::getContainer()->get('customer_loyalty.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        $loyaltyId = $repository->searchIds($criteria, $context)->firstId();
        static::assertNotNull($loyaltyId);

        $repository->update([['id' => $loyaltyId, 'pointsBalance' => $points]], $context);
    }
}

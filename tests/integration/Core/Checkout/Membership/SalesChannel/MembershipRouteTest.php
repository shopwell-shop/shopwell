<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Membership\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * The membership page reads what can be bought and what the customer holds - a separate payload from the
 * rewards page, so a shop can show one without the other.
 *
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class MembershipRouteTest extends TestCase
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
        $this->browser->request('GET', '/store-api/membership');

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
    }

    public function testANewCustomerSeesNoPlansAndNoSubscription(): void
    {
        $this->login($this->browser);

        $this->browser->request('GET', '/store-api/membership');

        $response = $this->json();

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertSame([], $response['plans']);
        static::assertSame([], $response['orders']);
        static::assertNull($response['subscription']);
    }

    public function testReturnsTheRunningMembership(): void
    {
        $customerId = $this->login($this->browser);
        $context = Context::createDefaultContext();

        $tierId = $this->createTier($context);

        $subscription = static::getContainer()
            ->get(MembershipSubscriptionService::class)
            ->grant($customerId, $tierId, 30, $context);

        $this->browser->request('GET', '/store-api/membership');

        $response = $this->json();

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertIsArray($response['subscription']);
        static::assertSame($subscription->getId(), $response['subscription']['id']);
        static::assertSame($tierId, $response['subscription']['tierId']);
        static::assertSame($subscription->getEndsAt()->format(\DATE_ATOM), $response['subscription']['endsAt']);
    }

    /**
     * The rewards page must not carry the offer any more: the split is what keeps a shop that only runs
     * points from loading membership tables on every account request.
     */
    public function testTheLoyaltyRouteNoLongerCarriesTheOffer(): void
    {
        $this->login($this->browser);

        $this->browser->request('GET', '/store-api/loyalty');

        $response = $this->json();

        static::assertArrayNotHasKey('plans', $response);
        static::assertArrayNotHasKey('membershipChannels', $response);
        static::assertArrayNotHasKey('membershipPurchases', $response);
        static::assertArrayHasKey('pointsBalance', $response);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        return json_decode(
            (string) $this->browser->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    private function createTier(Context $context): string
    {
        $tierId = Uuid::randomHex();

        static::getContainer()->get('loyalty_tier.repository')->create([
            [
                'id' => $tierId,
                'technicalName' => 'gold',
                'level' => 2,
                'minTierPoints' => 50,
                'active' => true,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Gold',
                    ],
                ],
            ],
        ], $context);

        return $tierId;
    }
}

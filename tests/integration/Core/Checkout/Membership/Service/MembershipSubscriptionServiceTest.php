<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Membership\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionEntity;
use Shopwell\Core\Checkout\Membership\MembershipSubscriptionStates;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\TestDefaults;

/**
 * The membership against the real database: a paid order has to end up as one row here and as a tier
 * on the loyalty account, a renewal has to keep it one row, and an expired membership has to give the
 * tier back to the growth value.
 *
 * @internal
 */
#[Package('checkout')]
class MembershipSubscriptionServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<MembershipSubscriptionCollection>
     */
    private EntityRepository $subscriptions;

    /**
     * @var EntityRepository<CustomerLoyaltyCollection>
     */
    private EntityRepository $loyalty;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->subscriptions = static::getContainer()->get('membership_subscription.repository');
        $this->loyalty = static::getContainer()->get('customer_loyalty.repository');
    }

    public function testAPaidOrderBecomesAMembershipOnTheCustomer(): void
    {
        $customerId = $this->createCustomer();
        $tierId = $this->createTier('gold', 1);

        $subscription = $this->service()->open($this->order($customerId, $tierId, 30), $this->context);

        static::assertSame($customerId, $subscription->getCustomerId());
        static::assertSame($tierId, $subscription->getLoyaltyTierId());
        static::assertSame(MembershipSubscriptionStates::STATE_ACTIVE, $subscription->getState());
        static::assertSame(
            30,
            (int) $subscription->getStartsAt()->diff($subscription->getEndsAt())->format('%a')
        );

        // the account carries the membership without owning it
        $account = $this->accountOf($customerId);
        static::assertSame($tierId, $account->getMembershipTierId());
        static::assertSame(
            $subscription->getEndsAt()->format('Y-m-d H:i'),
            $account->getMembershipValidUntil()?->format('Y-m-d H:i')
        );
    }

    /**
     * A customer who renews keeps one membership: the second purchase pushes its end instead of
     * opening a second row next to it.
     */
    public function testRenewingExtendsTheSameMembership(): void
    {
        $customerId = $this->createCustomer();
        $tierId = $this->createTier('gold', 1);

        $first = $this->service()->open($this->order($customerId, $tierId, 30), $this->context);
        $renewed = $this->service()->open($this->order($customerId, $tierId, 30), $this->context);

        static::assertSame($first->getId(), $renewed->getId());
        static::assertCount(1, $this->subscriptionsOf($customerId));
        static::assertSame(
            60,
            (int) $renewed->getStartsAt()->diff($renewed->getEndsAt())->format('%a')
        );
    }

    /**
     * The end of a membership is an act, not a date: the task ends it, and the tier falls back to what
     * the growth value earns - here to no tier at all, because the customer has not earned one.
     */
    public function testExpiringTakesTheTierAwayAgain(): void
    {
        $customerId = $this->createCustomer();
        $tierId = $this->createTier('gold', 5);

        $this->subscriptions->create([[
            'id' => Uuid::randomHex(),
            'customerId' => $customerId,
            'loyaltyTierId' => $tierId,
            'planName' => 'Gold',
            'amount' => 199.0,
            'currency' => 'CNY',
            'state' => MembershipSubscriptionStates::STATE_ACTIVE,
            'startsAt' => new \DateTimeImmutable('-60 days'),
            'endsAt' => new \DateTimeImmutable('-1 day'),
        ]], $this->context);

        $this->loyalty->update([[
            'id' => $this->accountOf($customerId)->getId(),
            'membershipTierId' => $tierId,
            'membershipValidUntil' => new \DateTimeImmutable('-1 day'),
        ]], $this->context);

        // the task sweeps the whole table, so what is asserted below is this membership, not the count
        static::assertGreaterThanOrEqual(1, $this->service()->expireDue($this->context));

        $subscription = $this->subscriptionsOf($customerId)->first();
        static::assertInstanceOf(MembershipSubscriptionEntity::class, $subscription);
        static::assertSame(MembershipSubscriptionStates::STATE_ENDED, $subscription->getState());
        static::assertSame(MembershipSubscriptionStates::END_EXPIRED, $subscription->getEndReason());

        $account = $this->accountOf($customerId);
        static::assertNull($account->getMembershipTierId());
        static::assertNull($account->getMembershipValidUntil());

        // a second run finds nothing left to do
        static::assertSame(0, $this->service()->expireDue($this->context));
    }

    private function service(): MembershipSubscriptionService
    {
        return static::getContainer()->get(MembershipSubscriptionService::class);
    }

    /**
     * The order is written first because the membership points back at it: a membership that names an
     * order nobody ever wrote is not a thing the database allows, and neither should the code.
     *
     * @return array<string, mixed>
     */
    private function order(string $customerId, string $tierId, int $durationDays): array
    {
        $orderId = Uuid::randomHex();

        static::getContainer()->get('membership_order.repository')->create([[
            'id' => $orderId,
            'customerId' => $customerId,
            'loyaltyTierId' => $tierId,
            'planName' => 'Gold',
            'amount' => 199.0,
            'currencyId' => Defaults::CURRENCY,
            'currency' => 'CNY',
            'durationDays' => $durationDays,
            'state' => MembershipOrderEntity::STATE_OPEN,
        ]], $this->context);

        return [
            'id' => $orderId,
            'customer_id' => $customerId,
            'membership_plan_id' => null,
            'loyalty_tier_id' => $tierId,
            'plan_name' => 'Gold',
            'amount' => 199.0,
            'currency' => 'CNY',
            'duration_days' => $durationDays,
        ];
    }

    private function accountOf(string $customerId): CustomerLoyaltyEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        $account = $this->loyalty->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(CustomerLoyaltyEntity::class, $account);

        return $account;
    }

    private function subscriptionsOf(string $customerId): MembershipSubscriptionCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        return $this->subscriptions->search($criteria, $this->context)->getEntities();
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        static::getContainer()->get('customer.repository')->create([[
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'gender' => $this->getValidGender(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'gender' => $this->getValidGender(),
            'customerNumber' => 'C' . $customerId,
        ]], $this->context);

        return $customerId;
    }

    private function createTier(string $technicalName, int $level): string
    {
        $tierId = Uuid::randomHex();

        // the technical name is unique in the database and this test does not clean up after itself,
        // so a rerun against the same test database would collide with the row of the run before
        static::getContainer()->get('loyalty_tier.repository')->create([[
            'id' => $tierId,
            'technicalName' => $technicalName . '-' . $tierId,
            'level' => $level,
            'active' => true,
            'minGrowthValue' => 1000,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => ucfirst($technicalName)],
            ],
        ]], $this->context);

        return $tierId;
    }
}

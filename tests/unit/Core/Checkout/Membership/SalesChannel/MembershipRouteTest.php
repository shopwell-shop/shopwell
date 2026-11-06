<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionEntity;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipRoute;
use Shopwell\Core\Checkout\Membership\Service\MembershipOfferService;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentContextResolver;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipRoute::class)]
class MembershipRouteTest extends TestCase
{
    #[DataProvider('subscriptionCases')]
    public function testReadsTheCustomerOfferAndPersistedSubscription(bool $hasSubscription): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getCurrency()->setIsoCode('CNY');
        $customer = new CustomerEntity();
        $customer->setId('customer');
        $request = new Request();
        $resolver = new StorefrontPaymentContextResolver();
        $offer = $this->createMock(MembershipOfferService::class);
        $offer->expects($this->once())->method('forCustomer')->with(
            'customer',
            $context->getCurrency()->getId(),
            $resolver->resolve($request, $context->getCurrency()->getIsoCode()),
            5,
            $context->getContext(),
        )->willReturn(['plans' => [['id' => 'plan']], 'channels' => [], 'orders' => []]);
        $subscription = null;
        if ($hasSubscription) {
            $subscription = new MembershipSubscriptionEntity();
            $subscription->setId('subscription');
            $subscription->setPlanName('Purchased name');
            $subscription->setLoyaltyTierId('tier');
            $subscription->setState('active');
            $subscription->setStartsAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
            $subscription->setEndsAt(new \DateTimeImmutable('2026-02-01T00:00:00+00:00'));
            $subscription->setAmount(10.0);
            $subscription->setCurrency('CNY');
        }
        $subscriptions = $this->createMock(MembershipSubscriptionService::class);
        $subscriptions->expects($this->once())->method('current')->with('customer', $context->getContext())->willReturn($subscription);
        $route = new MembershipRoute($offer, $subscriptions, new ExtensionDispatcher(new EventDispatcher()), $resolver);
        $data = $route->load($request, $context, $customer)->getMembership()->all();
        static::assertSame([['id' => 'plan']], $data['plans']);
        static::assertSame([], $data['channels']);
        static::assertSame([], $data['orders']);
        static::assertSame($hasSubscription ? [
            'id' => 'subscription', 'planName' => 'Purchased name', 'tierId' => 'tier', 'state' => 'active',
            'startsAt' => '2026-01-01T00:00:00+00:00', 'endsAt' => '2026-02-01T00:00:00+00:00', 'amount' => 10.0, 'currency' => 'CNY',
        ] : null, $data['subscription']);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function subscriptionCases(): iterable
    {
        yield 'without membership' => [false];
        yield 'with membership' => [true];
    }
}

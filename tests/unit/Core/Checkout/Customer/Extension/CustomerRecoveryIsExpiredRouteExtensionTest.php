<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Extension\CustomerRecoveryIsExpiredRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Test\Generator;
use Shopwell\Tests\Examples\CustomerRecoveryIsExpiredRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerRecoveryIsExpiredRouteExtension::class)]
class CustomerRecoveryIsExpiredRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesExpiryCheck(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new CustomerRecoveryIsExpiredRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: CustomerRecoveryIsExpiredRouteExtension::NAME,
            extension: new CustomerRecoveryIsExpiredRouteExtension(
                new RequestDataBag(),
                Generator::generateSalesChannelContext(),
            ),
            function: static function () use (&$coreCalled): CustomerRecoveryIsExpiredResponse {
                $coreCalled = true;

                return new CustomerRecoveryIsExpiredResponse(true);
            },
        );

        static::assertFalse($coreCalled, 'The core expiry check must be skipped when a subscriber resolves it.');
        static::assertFalse($result->isExpired());
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\RegisterRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Test\Generator;
use Shopwell\Tests\Examples\RegisterRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RegisterRouteExtension::class)]
class RegisterRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesRegistration(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RegisterRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: RegisterRouteExtension::NAME,
            extension: new RegisterRouteExtension(
                new RequestDataBag(),
                Generator::generateSalesChannelContext(),
                validateStorefrontUrl: true,
                additionalValidationDefinitions: null,
            ),
            function: static function () use (&$coreCalled): CustomerResponse {
                $coreCalled = true;

                return new CustomerResponse((new CustomerEntity())->assign(['id' => 'core']));
            },
        );

        static::assertFalse($coreCalled, 'The core registration must be skipped when a subscriber resolves it.');
        static::assertSame('example', $result->getCustomer()->getId());
    }
}

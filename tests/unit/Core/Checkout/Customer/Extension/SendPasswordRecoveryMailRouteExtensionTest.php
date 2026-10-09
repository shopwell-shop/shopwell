<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Extension\SendPasswordRecoveryMailRouteExtension;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Shopwell\Core\Test\Generator;
use Shopwell\Tests\Examples\SendPasswordRecoveryMailRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SendPasswordRecoveryMailRouteExtension::class)]
class SendPasswordRecoveryMailRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesRecoveryMail(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendPasswordRecoveryMailRouteExample());

        $coreCalled = false;
        (new ExtensionDispatcher($dispatcher))->publish(
            name: SendPasswordRecoveryMailRouteExtension::NAME,
            extension: new SendPasswordRecoveryMailRouteExtension(
                new RequestDataBag(),
                Generator::generateSalesChannelContext(),
                validateStorefrontUrl: true,
            ),
            function: static function () use (&$coreCalled): SuccessResponse {
                $coreCalled = true;

                return new SuccessResponse();
            },
        );

        static::assertFalse($coreCalled, 'The core recovery flow must be skipped when a subscriber resolves it.');
    }
}

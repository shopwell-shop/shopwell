<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Extension\ResetPasswordRouteExtension;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Shopwell\Core\Test\Generator;
use Shopwell\Tests\Examples\ResetPasswordRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ResetPasswordRouteExtension::class)]
class ResetPasswordRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesReset(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetPasswordRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ResetPasswordRouteExtension::NAME,
            extension: new ResetPasswordRouteExtension(
                new RequestDataBag(),
                Generator::generateSalesChannelContext(),
            ),
            function: static function () use (&$coreCalled): SuccessResponse {
                $coreCalled = true;

                return new SuccessResponse();
            },
        );

        static::assertFalse($coreCalled, 'The core reset flow must be skipped when a subscriber resolves it.');
    }
}

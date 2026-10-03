<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\Extension\CustomerRecoveryIsExpiredRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerRecoveryIsExpiredRoute::class)]
class CustomerRecoveryIsExpiredRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $response = new CustomerRecoveryIsExpiredResponse(false);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('customer-recovery-is-expired-route.load.pre', static function (CustomerRecoveryIsExpiredRouteExtension $extension) use ($data, $context, $response): void {
            static::assertSame(['data' => $data, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CustomerRecoveryIsExpiredRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(ClockInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($data, $context));
    }
}

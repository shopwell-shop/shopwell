<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\ChangePasswordRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangePasswordRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ChangePasswordRoute::class)]
class ChangePasswordRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $requestDataBag = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new ContextTokenResponse('token');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('change-password-route.change.pre', static function (ChangePasswordRouteExtension $extension) use ($requestDataBag, $context, $customer, $response): void {
            static::assertSame(['requestDataBag' => $requestDataBag, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ChangePasswordRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(DataValidator::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->change($requestDataBag, $context, $customer));
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\OrderTransactionProvider;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<OrderTransactionProvider>
 */
#[Package('after-sales')]
#[CoversClass(OrderTransactionProvider::class)]
class OrderTransactionProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): OrderTransactionProvider {
        return new OrderTransactionProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return OrderTransactionDefinition::ENTITY_NAME;
    }
}

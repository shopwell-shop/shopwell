<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\CustomerGroupProvider;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<CustomerGroupProvider>
 */
#[Package('after-sales')]
#[CoversClass(CustomerGroupProvider::class)]
class CustomerGroupProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): CustomerGroupProvider {
        return new CustomerGroupProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return CustomerGroupDefinition::ENTITY_NAME;
    }
}

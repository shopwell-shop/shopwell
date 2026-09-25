<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\CustomerRecoveryProvider;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<CustomerRecoveryProvider>
 */
#[Package('after-sales')]
#[CoversClass(CustomerRecoveryProvider::class)]
class CustomerRecoveryProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): CustomerRecoveryProvider {
        return new CustomerRecoveryProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return CustomerRecoveryDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return ['customer.salutation'];
    }
}

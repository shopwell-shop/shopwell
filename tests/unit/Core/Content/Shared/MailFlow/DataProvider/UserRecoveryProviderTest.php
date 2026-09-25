<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<UserRecoveryProvider>
 */
#[Package('after-sales')]
#[CoversClass(UserRecoveryProvider::class)]
class UserRecoveryProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): UserRecoveryProvider {
        return new UserRecoveryProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return UserRecoveryDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return ['user'];
    }
}

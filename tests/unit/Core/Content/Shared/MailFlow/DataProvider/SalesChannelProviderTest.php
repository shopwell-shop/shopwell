<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<SalesChannelProvider>
 */
#[Package('after-sales')]
#[CoversClass(SalesChannelProvider::class)]
class SalesChannelProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): SalesChannelProvider {
        return new SalesChannelProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return [
            'domains',
            'mailHeaderFooter',
        ];
    }
}

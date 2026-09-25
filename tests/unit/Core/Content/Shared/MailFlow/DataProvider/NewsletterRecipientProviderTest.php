<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\NewsletterRecipientProvider;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<NewsletterRecipientProvider>
 */
#[Package('after-sales')]
#[CoversClass(NewsletterRecipientProvider::class)]
class NewsletterRecipientProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): NewsletterRecipientProvider {
        return new NewsletterRecipientProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return NewsletterRecipientDefinition::ENTITY_NAME;
    }
}

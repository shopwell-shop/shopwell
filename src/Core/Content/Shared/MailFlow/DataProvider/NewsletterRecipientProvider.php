<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Shared\MailFlow\DataProvider;

use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<NewsletterRecipientEntity, NewsletterRecipientCollection>
 */
#[Package('after-sales')]
class NewsletterRecipientProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return NewsletterRecipientDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}

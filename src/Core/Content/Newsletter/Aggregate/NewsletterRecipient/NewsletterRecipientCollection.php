<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NewsletterRecipientEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class NewsletterRecipientCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'newsletter_recipient_collection';
    }

    protected function getExpectedClass(): string
    {
        return NewsletterRecipientEntity::class;
    }
}

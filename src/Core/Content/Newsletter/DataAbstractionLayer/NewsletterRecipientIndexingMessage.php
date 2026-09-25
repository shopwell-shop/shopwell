<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopwell\Core\Framework\Log\Package;

#[Package('after-sales')]
class NewsletterRecipientIndexingMessage extends EntityIndexingMessage
{
    private bool $deletedNewsletterRecipients = false;

    public function isDeletedNewsletterRecipients(): bool
    {
        return $this->deletedNewsletterRecipients;
    }

    public function setDeletedNewsletterRecipients(bool $deletedNewsletterRecipients): void
    {
        $this->deletedNewsletterRecipients = $deletedNewsletterRecipients;
    }
}

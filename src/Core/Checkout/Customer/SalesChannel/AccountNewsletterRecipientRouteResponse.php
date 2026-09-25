<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<AccountNewsletterRecipientResult>
 */
#[Package('checkout')]
class AccountNewsletterRecipientRouteResponse extends StoreApiResponse
{
    /**
     * @param EntitySearchResult<NewsletterRecipientCollection> $newsletterRecipients
     */
    public function __construct(EntitySearchResult $newsletterRecipients)
    {
        $firstNewsletterRecipient = $newsletterRecipients->getEntities()->first();
        if ($firstNewsletterRecipient) {
            $accNlRecipientResult = new AccountNewsletterRecipientResult($firstNewsletterRecipient->getStatus());
            parent::__construct($accNlRecipientResult);

            return;
        }
        $accNlRecipientResult = new AccountNewsletterRecipientResult();
        parent::__construct($accNlRecipientResult);
    }

    public function getAccountNewsletterRecipient(): AccountNewsletterRecipientResult
    {
        return $this->object;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\Event;

use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class NewsletterSubscribeUrlEvent extends Event implements ShopwellSalesChannelEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private string $subscribeUrl,
        private readonly string $hashedEmail,
        private readonly string $hash,
        private readonly array $data,
        private readonly NewsletterRecipientEntity $recipient
    ) {
    }

    public function getSubscribeUrl(): string
    {
        return $this->subscribeUrl;
    }

    public function setSubscribeUrl(string $subscribeUrl): void
    {
        $this->subscribeUrl = $subscribeUrl;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannelId();
    }

    public function getHashedEmail(): string
    {
        return $this->hashedEmail;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getRecipient(): NewsletterRecipientEntity
    {
        return $this->recipient;
    }
}

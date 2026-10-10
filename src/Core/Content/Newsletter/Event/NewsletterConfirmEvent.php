<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\Event;

use Shopwell\Core\Content\Flow\Dispatching\Aware\NewsletterRecipientAware;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Event\MailAware;
use Shopwell\Core\Framework\Event\SalesChannelAware;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\JsonSerializableTrait;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
class NewsletterConfirmEvent extends Event implements SalesChannelAware, MailAware, NewsletterRecipientAware, FlowEventAware
{
    use JsonSerializableTrait;

    final public const EVENT_NAME = 'newsletter.confirm';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly Context $context,
        private readonly NewsletterRecipientEntity $newsletterRecipient,
        private readonly string $salesChannelId
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(NewsletterRecipientAware::NEWSLETTER_RECIPIENT, new EntityType(NewsletterRecipientDefinition::class));
    }

    public function getNewsletterRecipient(): NewsletterRecipientEntity
    {
        return $this->newsletterRecipient;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->newsletterRecipient->getEmail() => $this->newsletterRecipient->getName() ?? '',
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getNewsletterRecipientId(): string
    {
        return $this->newsletterRecipient->getId();
    }
}

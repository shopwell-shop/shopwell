<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\DataAbstractionLayer;

use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopwell\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Shopwell\Core\Content\Newsletter\Event\NewsletterRecipientIndexerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class NewsletterRecipientIndexer extends EntityIndexer
{
    final public const CUSTOMER_NEWSLETTER_SALES_CHANNELS_UPDATER = 'newsletter_recipients.customer-newsletter-sales-channels';

    /**
     * @internal
     *
     * @param EntityRepository<NewsletterRecipientCollection> $repository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly CustomerNewsletterSalesChannelsUpdater $customerNewsletterSalesChannelsUpdater,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getName(): string
    {
        return 'newsletter_recipient.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new NewsletterRecipientIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(NewsletterRecipientDefinition::ENTITY_NAME);

        if ($updates === []) {
            return null;
        }

        return new NewsletterRecipientIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids)) {
            return;
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === [] || !$message instanceof NewsletterRecipientIndexingMessage) {
            return;
        }

        $context = $message->getContext();

        if ($message->allow(self::CUSTOMER_NEWSLETTER_SALES_CHANNELS_UPDATER)) {
            if ($message->isDeletedNewsletterRecipients()) {
                $this->customerNewsletterSalesChannelsUpdater->delete($ids);
            } else {
                $this->customerNewsletterSalesChannelsUpdater->update($ids);
            }
        }

        $this->eventDispatcher->dispatch(new NewsletterRecipientIndexerEvent($ids, $context, array_values($message->getSkip())));
    }

    public function getOptions(): array
    {
        return [
            self::CUSTOMER_NEWSLETTER_SALES_CHANNELS_UPDATER,
        ];
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }
}

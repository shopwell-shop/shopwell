<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework\Indexing;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopwell\Core\Framework\MessageQueue\DeduplicatableMessageInterface;
use Shopwell\Core\Framework\Util\Hasher;

#[Package('framework')]
class ElasticsearchIndexingMessage implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IndexingDto $data,
        private readonly ?IndexerOffset $offset,
        private readonly Context $context,
        private bool $lastMessage = false
    ) {
    }

    public function getData(): IndexingDto
    {
        return $this->data;
    }

    public function getOffset(): ?IndexerOffset
    {
        return $this->offset;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @experimental stableVersion:v6.8.0 feature:DEDUPLICATABLE_MESSAGES
     */
    public function deduplicationId(): ?string
    {
        $ids = $this->data->getIds();
        sort($ids);

        $data = serialize([
            $this->data->getEntity(),
            $this->data->getIndex(),
            $ids,
            $this->offset, // is not JSON serializable, so we use serialize
            $this->context, // relying on __serialize() to skip extensions
        ]);

        return Hasher::hash($data);
    }

    public function isLastMessage(): bool
    {
        return $this->lastMessage;
    }

    public function markAsLastMessage(): bool
    {
        return $this->lastMessage = true;
    }
}

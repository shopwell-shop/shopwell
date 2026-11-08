<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Shopwell\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[AsEventListener]
class SystemUpdateListener
{
    public const CONFIG_KEY = 'elasticsearch.indexing.entities';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractKeyValueStorage $storage,
        private readonly ElasticsearchIndexer $indexer,
        private readonly MessageBusInterface $messageBus,
        private readonly IndexMappingUpdater $mappingUpdater,
        private readonly LoggerInterface $logger,
        private readonly ElasticsearchRegistry $registry,
    ) {
    }

    public function __invoke(UpdatePostFinishEvent $event): void
    {
        try {
            $this->mappingUpdater->update($event->getContext());
        } catch (\Exception $exception) {
            $this->logger->error('Elasticsearch mapping update failed, the system update continues', ['exception' => $exception]);
        }

        try {
            $this->dispatchReindex();
        } catch (\Exception $exception) {
            $this->logger->error('Elasticsearch reindex could not be scheduled, the system update continues', ['exception' => $exception]);
        }
    }

    private function dispatchReindex(): void
    {
        $entitiesToReindex = $this->storage->get(self::CONFIG_KEY, []);

        if (\is_string($entitiesToReindex)) {
            $entitiesToReindex = \json_decode($entitiesToReindex, true);
        }

        if ($entitiesToReindex === []) {
            return;
        }

        if (!\is_array($entitiesToReindex) || \in_array('*', $entitiesToReindex, true)) {
            $entitiesToReindex = [];
        } else {
            $registeredEntities = \is_array($this->registry->getDefinitionNames())
                ? $this->registry->getDefinitionNames()
                : iterator_to_array($this->registry->getDefinitionNames());

            $entitiesToReindex = array_values(array_intersect($entitiesToReindex, $registeredEntities));

            if ($entitiesToReindex === []) {
                $this->storage->remove(self::CONFIG_KEY);

                return;
            }
        }

        $messagesToDispatch = [];
        $offset = null;
        while ($message = $this->indexer->iterate($offset, $entitiesToReindex)) {
            $offset = $message->getOffset();

            $messagesToDispatch[] = $message;
        }

        $lastMessage = end($messagesToDispatch);

        if (!$lastMessage instanceof ElasticsearchIndexingMessage) {
            return;
        }

        $lastMessage->markAsLastMessage();

        foreach ($messagesToDispatch as $message) {
            $this->messageBus->dispatch($message);
        }

        $this->storage->remove(self::CONFIG_KEY);
    }
}

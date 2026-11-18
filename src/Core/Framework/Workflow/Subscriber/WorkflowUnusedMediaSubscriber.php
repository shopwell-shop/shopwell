<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keep media referenced by submitted workflow forms from being purged.
 *
 * @internal
 */
#[Package('framework')]
final readonly class WorkflowUnusedMediaSubscriber implements EventSubscriberInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [UnusedMediaSearchEvent::class => 'markReferencedMedia'];
    }

    public function markReferencedMedia(UnusedMediaSearchEvent $event): void
    {
        foreach ($event->getUnusedIds() as $mediaId) {
            $used = $this->connection->fetchOne(
                'SELECT 1 FROM workflow_instance WHERE JSON_CONTAINS(form_data, JSON_QUOTE(:id)) LIMIT 1',
                ['id' => $mediaId],
                ['id' => ParameterType::STRING],
            );
            if ($used !== false) {
                $event->markAsUsed([$mediaId]);
            }
        }
    }
}

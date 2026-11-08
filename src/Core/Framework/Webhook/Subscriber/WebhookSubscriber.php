<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Subscriber;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\App\AppDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Service\WebhookStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Webhook\Subscriber\WebhookSubscriberTest
 */
#[Package('framework')]
class WebhookSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly WebhookStore $webhookStore)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'onAppWrite',
            AclRoleDefinition::ENTITY_NAME . '.deleted' => 'removeDeletedRoles',
        ];
    }

    public function onAppWrite(EntityWriteEvent $event): void
    {
        $commands = [];
        foreach ($event->getCommandsForEntity(AppDefinition::ENTITY_NAME) as $command) {
            if ($command instanceof UpdateCommand && $command->hasField('integration_id')) {
                $command->requestChangeSet();
                $commands[] = $command;
            }
        }

        if ($commands === []) {
            return;
        }

        $event->addSuccess(function () use ($commands): void {
            foreach ($commands as $command) {
                $changeSet = $command->getChangeSet();
                if ($changeSet === null || !$changeSet->hasChanged('integration_id')) {
                    continue;
                }

                $this->webhookStore->switchIntegration($changeSet->getBefore('integration_id'), $changeSet->getAfter('integration_id'));
            }
        });
    }

    public function removeDeletedRoles(EntityDeletedEvent $event): void
    {
        $this->webhookStore->removeDeletedRoles($event->getIds());
    }
}

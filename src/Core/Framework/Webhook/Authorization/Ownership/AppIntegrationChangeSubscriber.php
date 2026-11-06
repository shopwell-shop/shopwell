<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Authorization\Ownership;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\App\AppDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * When an app gets a new integration, for example on secret rotation, moves the webhooks owned by its
 * old integration to the new one. Otherwise they would be deleted together with the old integration.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Webhook\Authorization\Ownership\AppIntegrationChangeSubscriberTest
 */
#[Package('framework')]
class AppIntegrationChangeSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'onAppWrite',
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

                $this->connection->executeStatement(
                    'UPDATE `webhook` SET `owner_integration_id` = :new WHERE `owner_integration_id` = :old',
                    ['old' => $changeSet->getBefore('integration_id'), 'new' => $changeSet->getAfter('integration_id')],
                );
            }
        });
    }
}

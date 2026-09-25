<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Stock;

use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class AvailableStockMirrorSubscriber
{
    public function __invoke(EntityWriteEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $commands = $this->getAffected($event);

        foreach ($commands as $command) {
            $command->addPayload('available_stock', $command->getPayload()['stock'] ?? 0);
        }
    }

    /**
     * @return array<WriteCommand>
     */
    private function getAffected(EntityWriteEvent $event): array
    {
        return array_filter($event->getCommandsForEntity(ProductDefinition::ENTITY_NAME), static function (WriteCommand $command) {
            if ($command instanceof DeleteCommand) {
                return false;
            }

            if ($command instanceof InsertCommand) {
                return true;
            }

            return $command->hasField('stock');
        });
    }
}

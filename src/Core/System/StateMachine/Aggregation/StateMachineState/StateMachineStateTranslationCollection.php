<?php declare(strict_types=1);

namespace Shopwell\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<StateMachineStateTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StateMachineStateTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (StateMachineStateTranslationEntity $stateMachineStateTranslation) => $stateMachineStateTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (StateMachineStateTranslationEntity $stateMachineStateTranslation) => $stateMachineStateTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'state_machine_state_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineStateTranslationEntity::class;
    }
}

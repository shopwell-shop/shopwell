<?php declare(strict_types=1);

namespace Shopwell\Core\System\StateMachine;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<StateMachineTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StateMachineTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (StateMachineTranslationEntity $stateMachineTranslation) => $stateMachineTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (StateMachineTranslationEntity $stateMachineTranslation) => $stateMachineTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'state_machine_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineTranslationEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Position;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Position\Aggregate\PositionTranslation\PositionTranslationCollection;
use Shopwell\Core\System\User\Aggregate\UserPosition\UserPositionCollection;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class PositionEntity extends Entity
{
    use EntityIdTrait;

    protected int $position;

    protected ?bool $active = null;

    protected ?string $name = null;

    protected ?PositionTranslationCollection $translations = null;

    protected ?UserPositionCollection $userPositions = null;

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTranslations(): ?PositionTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(PositionTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getUserPositions(): ?UserPositionCollection
    {
        return $this->userPositions;
    }

    public function setUserPositions(UserPositionCollection $userPositions): void
    {
        $this->userPositions = $userPositions;
    }
}

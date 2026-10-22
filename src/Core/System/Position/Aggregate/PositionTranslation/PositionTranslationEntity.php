<?php declare(strict_types=1);

namespace Shopwell\Core\System\Position\Aggregate\PositionTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Position\PositionEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class PositionTranslationEntity extends TranslationEntity
{
    protected string $positionId;

    protected ?string $name = null;

    protected ?PositionEntity $position = null;

    public function getPositionId(): string
    {
        return $this->positionId;
    }

    public function setPositionId(string $positionId): void
    {
        $this->positionId = $positionId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): ?PositionEntity
    {
        return $this->position;
    }

    public function setPosition(?PositionEntity $position): void
    {
        $this->position = $position;
    }
}

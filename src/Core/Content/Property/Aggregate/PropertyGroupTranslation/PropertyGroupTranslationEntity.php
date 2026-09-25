<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Property\Aggregate\PropertyGroupTranslation;

use Shopwell\Core\Content\Property\PropertyGroupEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class PropertyGroupTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $propertyGroupId;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?int $position = null;

    protected ?PropertyGroupEntity $propertyGroup = null;

    public function getPropertyGroupId(): string
    {
        return $this->propertyGroupId;
    }

    public function setPropertyGroupId(string $propertyGroupId): void
    {
        $this->propertyGroupId = $propertyGroupId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPropertyGroup(): ?PropertyGroupEntity
    {
        return $this->propertyGroup;
    }

    public function setPropertyGroup(PropertyGroupEntity $propertyGroup): void
    {
        $this->propertyGroup = $propertyGroup;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }
}

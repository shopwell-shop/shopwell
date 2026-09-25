<?php declare(strict_types=1);

namespace Shopwell\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\DeliveryTime\DeliveryTimeEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class DeliveryTimeTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected ?DeliveryTimeEntity $deliveryTime = null;

    protected string $deliveryTimeId;

    protected ?string $name = null;

    public function getDeliveryTime(): ?DeliveryTimeEntity
    {
        return $this->deliveryTime;
    }

    public function setDeliveryTime(DeliveryTimeEntity $deliveryTime): void
    {
        $this->deliveryTime = $deliveryTime;
    }

    public function getDeliveryTimeId(): string
    {
        return $this->deliveryTimeId;
    }

    public function setDeliveryTimeId(string $deliveryTimeId): void
    {
        $this->deliveryTimeId = $deliveryTimeId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

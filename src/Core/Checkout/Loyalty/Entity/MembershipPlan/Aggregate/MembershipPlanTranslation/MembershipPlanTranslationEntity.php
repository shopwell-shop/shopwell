<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation;

use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipPlanTranslationEntity extends TranslationEntity
{
    use EntityIdTrait;

    protected string $membershipPlanId;

    protected string $name;

    protected ?string $description = null;

    protected ?MembershipPlanEntity $membershipPlan = null;

    public function getMembershipPlanId(): string
    {
        return $this->membershipPlanId;
    }

    public function setMembershipPlanId(string $membershipPlanId): void
    {
        $this->membershipPlanId = $membershipPlanId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getMembershipPlan(): ?MembershipPlanEntity
    {
        return $this->membershipPlan;
    }

    public function setMembershipPlan(MembershipPlanEntity $membershipPlan): void
    {
        $this->membershipPlan = $membershipPlan;
    }
}

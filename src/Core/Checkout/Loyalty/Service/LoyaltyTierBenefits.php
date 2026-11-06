<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitEntity;
use Shopwell\Core\Checkout\Loyalty\LoyaltyBenefitTypes;
use Shopwell\Core\Framework\Log\Package;

/**
 * What a tier grants, read as one value.
 *
 * The rows of a tier are turned into a value object once, so a consumer asks "how much does this tier
 * multiply my points by" instead of filtering a collection and remembering which type means what. The
 * object is immutable and knows nothing about the database - that is
 * {@see LoyaltyBenefitResolver}'s part.
 *
 * The accessors answer the *effective* benefit, not the stored row: a tier without an
 * `earn_multiplier` row grants a multiplier of 1.0, and one without a `cart_discount` row discounts
 * nothing. That is the meaning of an absent benefit, not a placeholder for "not configured yet" - a
 * tier that grants nothing is a complete answer, and callers must not have to null check it.
 */
#[Package('checkout')]
final class LoyaltyTierBenefits
{
    /**
     * @param array<string, float> $values the value of each type the tier grants
     * @param list<string> $order the types in the order they are meant to be shown
     */
    private function __construct(
        private readonly array $values,
        private readonly array $order,
    ) {
    }

    /**
     * A tier that grants nothing - the state of a shop that has not configured benefits yet.
     */
    public static function none(): self
    {
        return new self([], []);
    }

    public static function of(LoyaltyTierBenefitCollection $benefits): self
    {
        $sorted = array_values($benefits->getElements());

        usort(
            $sorted,
            static fn (LoyaltyTierBenefitEntity $a, LoyaltyTierBenefitEntity $b): int => [
                $a->getPosition(),
                $a->getType(),
            ] <=> [
                $b->getPosition(),
                $b->getType(),
            ]
        );

        $values = [];
        $order = [];

        foreach ($sorted as $benefit) {
            // the schema allows one row per type, this only keeps the object total if a caller hands
            // over a hand built collection: the first row wins, exactly like a duplicate key would
            if (\array_key_exists($benefit->getType(), $values)) {
                continue;
            }

            $values[$benefit->getType()] = $benefit->getValue();
            $order[] = $benefit->getType();
        }

        return new self($values, $order);
    }

    public function has(string $type): bool
    {
        return \array_key_exists($type, $this->values);
    }

    public function value(string $type): ?float
    {
        return $this->values[$type] ?? null;
    }

    public function earnMultiplier(): float
    {
        return $this->values[LoyaltyBenefitTypes::EARN_MULTIPLIER] ?? 1.0;
    }

    public function cartDiscountPercent(): float
    {
        return $this->values[LoyaltyBenefitTypes::CART_DISCOUNT] ?? 0.0;
    }

    public function grantsFreeShipping(): bool
    {
        return ($this->values[LoyaltyBenefitTypes::FREE_SHIPPING] ?? 0.0) > 0.0;
    }

    public function birthdayGiftAmount(): float
    {
        return $this->values[LoyaltyBenefitTypes::BIRTHDAY_GIFT] ?? 0.0;
    }

    public function pointsExpiryDays(): ?int
    {
        $days = $this->values[LoyaltyBenefitTypes::POINTS_EXPIRY_DAYS] ?? null;

        return $days === null ? null : (int) $days;
    }

    /**
     * @return array<string, float> the granted types and their values, in display order
     */
    public function all(): array
    {
        $values = [];

        foreach ($this->order as $type) {
            $values[$type] = $this->values[$type];
        }

        return $values;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Traits;

use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
trait LineItemGroupTestFixtureBehaviour
{
    private function buildGroup(string $packagerKey, float $value, string $sorterKey, RuleCollection $rules): LineItemGroupDefinition
    {
        $group = new LineItemGroupDefinition(
            Uuid::randomBytes(),
            $packagerKey,
            $value,
            $sorterKey,
            $rules
        );

        return $group;
    }
}

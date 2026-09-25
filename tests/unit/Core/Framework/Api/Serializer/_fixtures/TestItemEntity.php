<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\Serializer\_fixtures;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;

/**
 * @internal
 *
 * Simple test entity used as items in TestChildEntity.
 */
class TestItemEntity extends Entity
{
    public string $id;

    public ?string $name = null;
}

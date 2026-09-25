<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Api\ApiDefinition\EntityDefinition;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class SinceDefinition extends EntityDefinition
{
    public function since(): string
    {
        return '6.3.9.9';
    }

    public function getEntityName(): string
    {
        return 'since';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware()),
        ]);
    }
}

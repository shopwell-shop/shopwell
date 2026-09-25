<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\Framework\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class TestEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'test_entity';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new IdField('idAllowHtml', 'idAllowHtml'))->addFlags(new AllowHtml(false)),
            (new IdField('idAllowHtmlSanitized', 'idAllowHtmlSanitized'))->addFlags(new AllowHtml(true)),
        ]);
    }
}

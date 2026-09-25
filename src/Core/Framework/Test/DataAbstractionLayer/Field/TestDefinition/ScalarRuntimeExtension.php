<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class ScalarRuntimeExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new StringField('test', 'test'))->addFlags(new ApiAware(), new Runtime())
        );
    }

    public function getEntityName(): string
    {
        return 'extendable';
    }
}

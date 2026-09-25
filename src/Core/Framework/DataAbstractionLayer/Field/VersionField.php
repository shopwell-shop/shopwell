<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class VersionField extends FkField
{
    public function __construct()
    {
        parent::__construct('version_id', 'versionId', VersionDefinition::class);

        $this->addFlags(new PrimaryKey(), new Required());
    }

    protected function getSerializerClass(): string
    {
        return VersionFieldSerializer::class;
    }
}

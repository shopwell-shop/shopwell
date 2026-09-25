<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedAtFieldSerializer;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class CreatedAtField extends DateTimeField
{
    public function __construct()
    {
        parent::__construct('created_at', 'createdAt');
        $this->addFlags(new Required());
    }

    protected function getSerializerClass(): string
    {
        return CreatedAtFieldSerializer::class;
    }
}

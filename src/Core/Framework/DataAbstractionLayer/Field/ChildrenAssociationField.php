<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class ChildrenAssociationField extends OneToManyAssociationField
{
    public function __construct(
        string $referenceClass,
        string $propertyName = 'children'
    ) {
        parent::__construct($propertyName, $referenceClass, 'parent_id');
        $this->addFlags(new CascadeDelete());
    }
}

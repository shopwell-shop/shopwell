<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomEntity\Xml\Field;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class OneToOneField extends AssociationField
{
    use RequiredTrait;

    protected string $type = 'one-to-one';
}

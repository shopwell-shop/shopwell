<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomEntity\Xml\Field;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class OneToManyField extends AssociationField
{
    protected string $type = 'one-to-many';

    protected bool $reverseRequired = false;

    public function isReverseRequired(): bool
    {
        return $this->reverseRequired;
    }
}

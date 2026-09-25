<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomEntity\Xml\Field\Traits;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
trait RequiredTrait
{
    protected bool $required = false;

    public function isRequired(): bool
    {
        return $this->required;
    }
}

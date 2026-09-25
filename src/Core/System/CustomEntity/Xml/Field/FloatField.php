<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomEntity\Xml\Field;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;
use Shopwell\Core\System\CustomEntity\Xml\Field\Traits\TranslatableTrait;

/**
 * @internal
 */
#[Package('framework')]
class FloatField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected string $type = 'float';

    protected ?float $default = null;

    public function getDefault(): ?float
    {
        return $this->default;
    }
}

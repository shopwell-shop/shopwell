<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomEntity\Xml\Field;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;
use Shopwell\Core\System\CustomEntity\Xml\Field\Traits\TranslatableTrait;

/**
 * @internal
 */
#[Package('framework')]
class TextField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected bool $allowHtml = false;

    protected string $type = 'text';

    protected ?string $default = null;

    public function allowHtml(): bool
    {
        return $this->allowHtml;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }
}

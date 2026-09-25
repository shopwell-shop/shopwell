<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomEntity\Xml\Field;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class LabelField extends Field
{
    protected string $type = 'label';
}

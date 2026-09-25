<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation\Requirements;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractRequirement implements Requirement
{
    public function required(Manifest $manifest): bool
    {
        return \in_array(static::name(), $manifest->getRequirements(), true);
    }
}

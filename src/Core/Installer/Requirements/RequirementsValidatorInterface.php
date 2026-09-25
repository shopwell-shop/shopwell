<?php declare(strict_types=1);

namespace Shopwell\Core\Installer\Requirements;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Installer\Requirements\Struct\RequirementsCheckCollection;

/**
 * @internal
 */
#[Package('framework')]
interface RequirementsValidatorInterface
{
    public function validateRequirements(RequirementsCheckCollection $checks): RequirementsCheckCollection;
}

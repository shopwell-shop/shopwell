<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Analysis;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class DeactivateValidationAnalysis extends Analysis
{
    public function validate(): bool
    {
        return false;
        // deactivate Validitation
    }
}

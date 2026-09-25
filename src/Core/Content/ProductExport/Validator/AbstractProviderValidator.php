<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Validator;

use Shopwell\Core\Content\ProductExport\Error\ErrorCollection;
use Shopwell\Core\Content\ProductExport\ProductExportEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
abstract class AbstractProviderValidator implements ValidatorInterface
{
    final public function validate(ProductExportEntity $productExportEntity, string $productExportContent, ErrorCollection $errors): void
    {
        if ($productExportEntity->getProvider() !== $this->getProviderTechnicalName()) {
            return;
        }

        $this->validateProviderExport($productExportEntity, $productExportContent, $errors);
    }

    abstract protected function getProviderTechnicalName(): string;

    abstract protected function validateProviderExport(ProductExportEntity $productExportEntity, string $productExportContent, ErrorCollection $errors): void;
}

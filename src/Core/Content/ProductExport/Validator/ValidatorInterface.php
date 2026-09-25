<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Validator;

use Shopwell\Core\Content\ProductExport\Error\ErrorCollection;
use Shopwell\Core\Content\ProductExport\ProductExportEntity;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
interface ValidatorInterface
{
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent, ErrorCollection $errors): void;
}

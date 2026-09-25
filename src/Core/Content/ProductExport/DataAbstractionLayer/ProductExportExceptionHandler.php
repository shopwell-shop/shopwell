<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\DataAbstractionLayer;

use Shopwell\Core\Content\ProductExport\Exception\DuplicateFileNameException;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductExportExceptionHandler implements ExceptionHandlerInterface
{
    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*file_name\'/', $e->getMessage())) {
            $file = [];
            preg_match('/Duplicate entry \'(.*)\' for key/', $e->getMessage(), $file);
            $file = $file[1] ?? '';

            return new DuplicateFileNameException($file, $e);
        }

        return null;
    }

    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }
}

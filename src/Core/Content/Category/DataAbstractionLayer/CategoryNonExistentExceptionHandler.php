<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\DataAbstractionLayer;

use Shopwell\Core\Content\Category\CategoryException;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class CategoryNonExistentExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1452 Cannot add or update a child row: a foreign key constraint fails.*category\.after_category_id/', $e->getMessage())) {
            return CategoryException::afterCategoryNotFound();
        }

        return null;
    }
}

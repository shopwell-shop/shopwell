<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductStream;

use Shopwell\Core\Content\ProductStream\Exception\EmptyProductStreamException;
use Shopwell\Core\Content\ProductStream\Exception\NoFilterException;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

#[Package('inventory')]
class ProductStreamException extends HttpException
{
    public static function productStreamNotFound(string $id): ShopwellHttpException
    {
        return new EntityNotFoundException('product_stream', $id);
    }

    public static function noFilters(string $id): ShopwellHttpException
    {
        return new NoFilterException($id);
    }

    public static function emptyProductStream(string $id): EmptyProductStreamException
    {
        return new EmptyProductStreamException($id);
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellException;

#[Package('framework')]
interface WriteFieldException extends ShopwellException
{
    public function getPath(): string;
}

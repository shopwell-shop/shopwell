<?php declare(strict_types=1);

namespace Shopwell\Core\Framework;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface ShopwellException extends \Throwable
{
    public function getErrorCode(): string;

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array;
}

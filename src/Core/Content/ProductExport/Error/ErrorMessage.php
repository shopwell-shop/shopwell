<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Error;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('inventory')]
class ErrorMessage extends Struct
{
    protected string $message;

    protected ?int $line = null;

    protected ?int $column = null;

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function getColumn(): ?int
    {
        return $this->column;
    }

    public function getApiAlias(): string
    {
        return 'product_export_error_message';
    }
}

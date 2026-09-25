<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency;

use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopwell\Core\Framework\Log\Package;

#[Package('fundamentals@framework')]
class CurrencyExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (\preg_match('/SQLSTATE\\[23000\\]:.*1062 Duplicate entry \'(?<isoCode>.*)\' for key \'(?:currency\\.)?uniq\\.currency\\.iso_code\'/', $e->getMessage(), $matches)) {
            return CurrencyException::isoCodeNotUnique($matches['isoCode'], $e);
        }

        return null;
    }
}

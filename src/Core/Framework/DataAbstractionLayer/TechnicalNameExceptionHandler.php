<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer;

use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Checkout\Shipping\ShippingException;
use Shopwell\Core\Content\ImportExport\ImportExportException;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class TechnicalNameExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (\preg_match(
            '/SQLSTATE\[23000]: Integrity constraint violation: 1062 Duplicate entry \'(?<technicalName>.*)\' for key \'payment_method.uniq\.technical_name\'/',
            $e->getMessage(),
            $matches
        )) {
            return PaymentException::duplicateTechnicalName($matches['technicalName']);
        }

        if (\preg_match(
            '/SQLSTATE\[23000]: Integrity constraint violation: 1062 Duplicate entry \'(?<technicalName>.*)\' for key \'shipping_method.uniq\.technical_name\'/',
            $e->getMessage(),
            $matches
        )) {
            return ShippingException::duplicateTechnicalName($matches['technicalName']);
        }

        if (\preg_match(
            '/SQLSTATE\[23000]: Integrity constraint violation: 1062 Duplicate entry \'(?<technicalName>.*)\' for key \'import_export_profile\.uniq\.import_export_profile\.technical_name\'/',
            $e->getMessage(),
            $matches
        )) {
            return ImportExportException::duplicateTechnicalName($matches['technicalName']);
        }

        return null;
    }
}

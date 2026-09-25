<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Validation;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellException;
use Symfony\Component\Validator\ConstraintViolationList;

#[Package('framework')]
interface ConstraintViolationExceptionInterface extends ShopwellException
{
    public function getViolations(): ConstraintViolationList;
}

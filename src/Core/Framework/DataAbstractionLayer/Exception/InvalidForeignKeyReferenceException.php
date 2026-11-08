<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
#[Package('framework')]
class InvalidForeignKeyReferenceException extends WriteConstraintViolationException
{
    public const ERROR_CODE = 'FRAMEWORK__INVALID_FOREIGN_KEY_REFERENCE';
}

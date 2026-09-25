<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class RuntimeFieldInCriteriaException extends ShopwellHttpException
{
    public function __construct(string $field)
    {
        parent::__construct(
            'Field {{ field }} is a Runtime field and cannot be used in a criteria',
            ['field' => $field]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__RUNTIME_FIELD_IN_CRITERIA';
    }
}

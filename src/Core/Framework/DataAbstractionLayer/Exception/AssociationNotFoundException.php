<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use {DomainException}::associationNotFound() instead
 */
#[Package('framework')]
class AssociationNotFoundException extends ShopwellHttpException
{
    public function __construct(string $field)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'Can not find association by name {{ association }}',
            ['association' => $field]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__ASSOCIATION_NOT_FOUND';
    }
}

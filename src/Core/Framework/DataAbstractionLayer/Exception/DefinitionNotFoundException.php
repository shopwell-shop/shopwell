<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class DefinitionNotFoundException extends ShopwellHttpException
{
    public function __construct(string $entity)
    {
        parent::__construct(
            'Definition for entity "{{ entityName }}" does not exist.',
            ['entityName' => $entity]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__DEFINITION_NOT_FOUND';
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NoEntityClonedException extends ShopwellHttpException
{
    public function __construct(
        string $entity,
        string $id
    ) {
        parent::__construct(
            'Could not clone entity {{ entity }} with id {{ id }}.',
            ['entity' => $entity, 'id' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__NO_ENTITIY_CLONED_ERROR';
    }
}

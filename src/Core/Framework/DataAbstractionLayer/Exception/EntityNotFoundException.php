<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class EntityNotFoundException extends ShopwellHttpException
{
    public function __construct(
        string $entity,
        string $identifier
    ) {
        parent::__construct(
            '{{ entity }} for id {{ identifier }} not found.',
            ['entity' => $entity, 'identifier' => $identifier]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__ENTITY_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}

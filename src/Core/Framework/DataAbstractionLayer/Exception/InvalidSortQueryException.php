<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class InvalidSortQueryException extends DataAbstractionLayerException
{
    public function __construct(?string $message = null, array $parameters = [])
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            DataAbstractionLayerException::INVALID_SORT_QUERY,
            $message ?? 'Invalid sort query',
            $parameters
        );
    }
}

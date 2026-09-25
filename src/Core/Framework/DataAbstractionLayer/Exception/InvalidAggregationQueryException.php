<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class InvalidAggregationQueryException extends DataAbstractionLayerException
{
    public function __construct(string $message)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__INVALID_AGGREGATION_QUERY',
            '{{ message }}',
            ['message' => $message]
        );
    }
}

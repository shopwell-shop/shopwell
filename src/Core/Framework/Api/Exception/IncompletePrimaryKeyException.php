<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class IncompletePrimaryKeyException extends ShopwellHttpException
{
    public function __construct(array $primaryKeyFields)
    {
        parent::__construct(
            'The primary key consists of {{ fieldCount }} fields. Please provide values for the following fields: {{ fieldsString }}',
            ['fieldCount' => \count($primaryKeyFields), 'fields' => $primaryKeyFields, 'fieldsString' => implode(', ', $primaryKeyFields)]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INCOMPLETE_PRIMARY_KEY';
    }
}

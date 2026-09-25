<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ExpectationFailedException extends ShopwellHttpException
{
    /**
     * @param list<string> $fails
     */
    public function __construct(array $fails)
    {
        parent::__construct('API Expectations failed', ['fails' => $fails]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__API_EXPECTATION_FAILED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_EXPECTATION_FAILED;
    }
}

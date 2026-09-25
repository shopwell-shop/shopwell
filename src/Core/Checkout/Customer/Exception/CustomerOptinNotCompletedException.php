<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Exception;

use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerOptinNotCompletedException extends CustomerException
{
    public function __construct(
        string $id,
        int $statusCode = Response::HTTP_UNAUTHORIZED,
        string $errorCode = self::CUSTOMER_OPTIN_NOT_COMPLETED,
    ) {
        parent::__construct(
            $statusCode,
            $errorCode,
            'The customer with the id "{{ customerId }}" has not completed the opt-in.',
            ['customerId' => $id]
        );
    }

    public function getSnippetKey(): string
    {
        return 'account.doubleOptinAccountAlert';
    }
}

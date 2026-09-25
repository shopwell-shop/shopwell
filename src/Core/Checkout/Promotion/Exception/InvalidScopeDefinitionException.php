<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use PromotionException::invalidScopeDefinition() instead
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class InvalidScopeDefinitionException extends ShopwellHttpException
{
    public function __construct(string $scope)
    {
        parent::__construct(
            'Invalid discount calculator scope definition "{{ label }}"',
            ['label' => $scope]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__INVALID_DISCOUNT_SCOPE_DEFINITION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}

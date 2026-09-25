<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Cart\Token;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\RegisteredClaims;
use Shopwell\Core\Checkout\Payment\Cart\Token\Constraint\PaymentTokenRegistered;
use Shopwell\Core\Framework\JWT\SalesChannel\JWTGenerator;
use Shopwell\Core\Framework\JWT\Struct\JWTStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * @extends JWTGenerator<PaymentToken>
 */
#[Package('checkout')]
class PaymentTokenGenerator extends JWTGenerator
{
    /**
     * @internal
     */
    public function __construct(
        Configuration $configuration,
        DataValidator $dataValidator,
        private readonly SystemConfigService $systemConfigService,
    ) {
        parent::__construct($configuration, $dataValidator);
    }

    protected function getTokenLifetime(JWTStruct $jwt): int
    {
        return ($this->systemConfigService->getInt('core.cart.paymentFinalizeTransactionTime', $jwt->salesChannelId) * 60) ?: 1800;
    }

    protected function getJWTStructClass(): string
    {
        return PaymentToken::class;
    }

    protected function getStructConstraints(): DataValidationDefinition
    {
        $definition = parent::getStructConstraints();
        $definition->add(RegisteredClaims::ID, new NotBlank(), new NotNull(), new PaymentTokenRegistered());
        $definition->add('salesChannelId', new NotBlank(), new NotNull());
        $definition->add('paymentMethodId', new NotBlank(), new NotNull());

        return $definition;
    }
}

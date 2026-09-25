<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Validation;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('checkout')]
class PasswordValidationFactory implements DataValidationFactoryInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('password.create');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('password.update');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, SalesChannelContext $context): void
    {
        $minLength = $this->systemConfigService->getInt('core.loginRegistration.passwordMinLength', $context->getSalesChannelId());
        if ($minLength < 0) {
            $minLength = null;
        }
        $definition->add('password', new NotBlank(), new Length(min: $minLength, max: PasswordHasherInterface::MAX_PASSWORD_LENGTH, minMessage: 'VIOLATION::PASSWORD_IS_TOO_SHORT', maxMessage: 'VIOLATION::PASSWORD_IS_TOO_LONG'));
    }
}

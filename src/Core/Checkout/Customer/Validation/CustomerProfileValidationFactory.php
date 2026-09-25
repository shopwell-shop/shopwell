<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Validation;

use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\Salutation\SalutationDefinition;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('checkout')]
class CustomerProfileValidationFactory implements DataValidationFactoryInterface
{
    /**
     * @param string[] $accountTypes
     *
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly array $accountTypes
    ) {
    }

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.profile.create');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.profile.update');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, SalesChannelContext $context): void
    {
        $definition
            ->add('salutationId', new EntityExists(entity: SalutationDefinition::ENTITY_NAME, context: $context->getContext()))
            ->add('title', new Length(max: CustomerDefinition::MAX_LENGTH_TITLE))
            ->add('firstName', new NotBlank(), new Length(max: CustomerDefinition::MAX_LENGTH_FIRST_NAME))
            ->add('lastName', new NotBlank(), new Length(max: CustomerDefinition::MAX_LENGTH_LAST_NAME))
            ->add('accountType', new Choice(choices: $this->accountTypes));

        $salesChannelId = $context->getSalesChannelId();

        if ($this->systemConfigService->get('core.loginRegistration.showBirthdayField', $salesChannelId)
            && $this->systemConfigService->get('core.loginRegistration.birthdayFieldRequired', $salesChannelId)) {
            $definition
                ->add('birthdayDay', new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 31))
                ->add('birthdayMonth', new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 12))
                ->add('birthdayYear', new GreaterThanOrEqual(value: 1900), new LessThanOrEqual(value: date('Y')));
        }
    }
}

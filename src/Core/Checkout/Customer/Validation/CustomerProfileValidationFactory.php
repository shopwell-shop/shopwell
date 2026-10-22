<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Validation;

use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerPhoneNumberUnique;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\Constraint\NoHtml;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
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

        // The registration only requires an email address and a password, the name is collected later.
        $this->addConstraints($definition, $context, requireName: false);

        return $definition;
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.profile.update');

        // Updating the profile still requires the name, as the profile page offers it as a mandatory field.
        $this->addConstraints($definition, $context, requireName: true);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, SalesChannelContext $context, bool $requireName): void
    {
        $definition
            ->add('gender', new Choice(choices: [DictionaryEntryDefinition::MALE, DictionaryEntryDefinition::FEMALE, DictionaryEntryDefinition::NOT_SPECIFIED]));

        if ($requireName) {
            $definition
                ->add('name', new NotBlank())
                ->add('name', new Length(max: CustomerDefinition::MAX_LENGTH_NAME));
        } else {
            $definition->add('name', new Length(max: CustomerDefinition::MAX_LENGTH_NAME));
        }

        $definition
            ->add('accountType', new Choice(choices: $this->accountTypes))
            ->add('phoneNumber', new Length(max: CustomerDefinition::MAX_LENGTH_PHONE_NUMBER, exactMessage: 'VIOLATION::PHONE_NUMBER_IS_TOO_LONG'))
            ->add('name', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('phoneNumber', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            // A number that two customers share would make the login by sms refuse both of them.
            ->add('phoneNumber', new CustomerPhoneNumberUnique(salesChannelContext: $context));

        $salesChannelId = $context->getSalesChannelId();

        if ($this->systemConfigService->get('core.loginRegistration.showBirthdayField', $salesChannelId)
            && $this->systemConfigService->get('core.loginRegistration.birthdayFieldRequired', $salesChannelId)) {
            $definition
                ->add('birthdayDay', new NotBlank(), new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 31))
                ->add('birthdayMonth', new NotBlank(), new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 12))
                ->add('birthdayYear', new NotBlank(), new GreaterThanOrEqual(value: 1900), new LessThanOrEqual(value: date('Y')));
        }
    }
}

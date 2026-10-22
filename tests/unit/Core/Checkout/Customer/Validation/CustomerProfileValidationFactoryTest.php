<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerPhoneNumberUnique;
use Shopwell\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\Constraint\NoHtml;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerProfileValidationFactory::class)]
class CustomerProfileValidationFactoryTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $accountTypes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountTypes = [CustomerEntity::ACCOUNT_TYPE_BUSINESS, CustomerEntity::ACCOUNT_TYPE_PRIVATE];
    }

    public function testCreateWithSalesChannelContext(): void
    {
        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->create($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.create');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext, requireName: false);

        static::assertEquals($expected, $actual);
    }

    public function testCreateWithSalesChannelContextButBirthdayFieldIsHidden(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => ['core.loginRegistration.showBirthdayField' => false],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->create($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.create');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext, requireName: false);

        static::assertEquals($expected, $actual);
    }

    public function testCreateWithSalesChannelContextButBirthdayFieldIsOptional(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => false,
            ],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->create($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.create');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext, requireName: false);

        static::assertEquals($expected, $actual);
    }

    public function testCreateWithSalesChannelContextButBirthdayFieldIsRequired(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => true,
            ],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->create($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.create');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext, requireName: false);
        $this->addConstraintsBirthday($expected);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithSalesChannelContext(): void
    {
        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->update($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.update');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithSalesChannelContextButBirthdayFieldIsHidden(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => ['core.loginRegistration.showBirthdayField' => false],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->update($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.update');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithSalesChannelContextButBirthdayFieldIsOptional(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => false,
            ],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->update($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.update');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithSalesChannelContextButBirthdayFieldIsRequired(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => true,
            ],
        ]);

        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();
        $actual = $customerProfileValidationFactory->update($salesChannelContext);
        $expected = new DataValidationDefinition('customer.profile.update');
        $this->addConstraintsSalesChannelContext($expected, $salesChannelContext);
        $this->addConstraintsBirthday($expected);

        static::assertEquals($expected, $actual);
    }

    public function testNameFieldsRejectHtml(): void
    {
        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $properties = $customerProfileValidationFactory->create($this->getSalesChannelContext())->getProperties();

        foreach (['name'] as $field) {
            static::assertArrayHasKey($field, $properties);

            $noHtml = array_values(array_filter($properties[$field], static fn ($constraint) => $constraint instanceof NoHtml));

            static::assertCount(1, $noHtml, \sprintf('Expected exactly one NoHtml constraint on "%s".', $field));
            static::assertSame('VIOLATION::CONTAINS_HTML_ERROR', $noHtml[0]->getMessage());
        }
    }

    public function testNameIsOnlyRequiredWhenUpdatingTheProfile(): void
    {
        $customerProfileValidationFactory = new CustomerProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $salesChannelContext = $this->getSalesChannelContext();

        // the registration only requires email and password, so the name must not be mandatory
        $createConstraints = $customerProfileValidationFactory->create($salesChannelContext)->getProperties()['name'];
        static::assertCount(0, array_filter($createConstraints, static fn ($constraint) => $constraint instanceof NotBlank));

        // the profile page offers the name as a mandatory field, which is why updating still requires it
        $updateConstraints = $customerProfileValidationFactory->update($salesChannelContext)->getProperties()['name'];
        static::assertCount(1, array_filter($updateConstraints, static fn ($constraint) => $constraint instanceof NotBlank));
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        return Generator::generateSalesChannelContext(
            salesChannel: $salesChannel,
        );
    }

    private function addConstraintsSalesChannelContext(
        DataValidationDefinition $definition,
        SalesChannelContext $context,
        bool $requireName = true
    ): void {
        $definition
            ->add('gender', new Choice(choices: [DictionaryEntryDefinition::MALE, DictionaryEntryDefinition::FEMALE, DictionaryEntryDefinition::NOT_SPECIFIED]));

        if ($requireName) {
            $definition->add('name', new NotBlank());
        }

        $definition
            ->add('name', new Length(max: CustomerDefinition::MAX_LENGTH_NAME))
            ->add('accountType', new Choice(choices: $this->accountTypes))
            ->add('phoneNumber', new Length(max: CustomerDefinition::MAX_LENGTH_PHONE_NUMBER, exactMessage: 'VIOLATION::PHONE_NUMBER_IS_TOO_LONG'))
            ->add('name', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('phoneNumber', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('phoneNumber', new CustomerPhoneNumberUnique(salesChannelContext: $context));
    }

    private function addConstraintsBirthday(DataValidationDefinition $definition): void
    {
        $definition
            ->add('birthdayDay', new NotBlank(), new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 31))
            ->add('birthdayMonth', new NotBlank(), new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 12))
            ->add('birthdayYear', new NotBlank(), new GreaterThanOrEqual(value: 1900), new LessThanOrEqual(value: date('Y')));
    }
}

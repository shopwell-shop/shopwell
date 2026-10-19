<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerPhoneNumberUnique;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerPhoneNumberUniqueValidator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @internal
 *
 * @extends ConstraintValidatorTestCase<CustomerPhoneNumberUniqueValidator>
 */
#[Package('checkout')]
#[CoversClass(CustomerPhoneNumberUniqueValidator::class)]
class CustomerPhoneNumberUniqueValidatorTest extends ConstraintValidatorTestCase
{
    private EntityRepository&Stub $customerRepository;

    protected function setUp(): void
    {
        $this->customerRepository = static::createStub(EntityRepository::class);

        parent::setUp();
    }

    public function testItIgnoresAnEmptyValue(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->never())->method('searchIds');

        $this->validateValueThatIsNotChecked($customerRepository, null);
        $this->assertNoViolation();

        $this->validateValueThatIsNotChecked($customerRepository, '');
        $this->assertNoViolation();
    }

    public function testItIgnoresABlankValue(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->never())->method('searchIds');

        $this->validateValueThatIsNotChecked($customerRepository, " \u{3000} ");
        $this->assertNoViolation();
    }

    public function testItChecksTheNumberAgainstTheActiveCustomersOfTheNumber(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('searchIds')
            ->with(
                static::callback(static function (Criteria $criteria): bool {
                    static::assertSame(1, $criteria->getLimit());

                    $filters = $criteria->getFilters();
                    static::assertCount(1, $filters);

                    $filter = $filters[0];
                    static::assertInstanceOf(MultiFilter::class, $filter);
                    static::assertSame(MultiFilter::CONNECTION_AND, $filter->getOperator());

                    $expected = [
                        ['phoneNumber', '0123456789'],
                        ['active', true],
                        ['guest', false],
                    ];

                    $queries = $filter->getQueries();
                    static::assertCount(\count($expected), $queries);

                    foreach ($expected as $index => [$field, $value]) {
                        $query = $queries[$index];
                        static::assertInstanceOf(EqualsFilter::class, $query);
                        static::assertSame($field, $query->getField());
                        static::assertSame($value, $query->getValue());
                    }

                    return true;
                }),
                static::isInstanceOf(Context::class),
            )
            ->willReturn($this->createSearchResult(null));

        $this->validateValue($customerRepository, ' 0123456789 ');

        $this->assertNoViolation();
    }

    public function testItDoesNotConflictWithTheLoggedInCustomerItself(): void
    {
        $customerId = 'customer-of-the-context';

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('searchIds')
            ->with(
                static::callback(static function (Criteria $criteria) use ($customerId): bool {
                    $filter = $criteria->getFilters()[0];
                    static::assertInstanceOf(MultiFilter::class, $filter);

                    $queries = $filter->getQueries();
                    static::assertCount(4, $queries);

                    $exclusion = $queries[3];
                    static::assertInstanceOf(NotFilter::class, $exclusion);

                    $excludedQueries = $exclusion->getQueries();
                    static::assertCount(1, $excludedQueries);
                    static::assertInstanceOf(EqualsFilter::class, $excludedQueries[0]);
                    static::assertSame('id', $excludedQueries[0]->getField());
                    static::assertSame($customerId, $excludedQueries[0]->getValue());

                    return true;
                }),
                static::isInstanceOf(Context::class),
            )
            ->willReturn($this->createSearchResult(null));

        $this->validateValue($customerRepository, '0123456789', $customerId);

        $this->assertNoViolation();
    }

    public function testItBuildsAViolationWhenTheNumberBelongsToAnotherCustomer(): void
    {
        $phoneNumber = '0123456789';

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn($this->createSearchResult('other-customer'));

        $constraint = $this->createConstraint();

        $validator = new CustomerPhoneNumberUniqueValidator($customerRepository);
        $validator->initialize($this->context);
        $validator->validate($phoneNumber, $constraint);

        $violations = $this->context->getViolations();
        static::assertCount(1, $violations);

        $violation = $violations[0];
        // The storefront translates the message template, so it has to be the snippet key.
        static::assertSame(CustomerPhoneNumberUnique::CUSTOMER_PHONE_NUMBER_NOT_UNIQUE_CODE, $violation->getMessageTemplate());
        static::assertSame(CustomerPhoneNumberUnique::CUSTOMER_PHONE_NUMBER_NOT_UNIQUE_CODE, $violation->getCode());
    }

    public function testItRejectsAnUnexpectedConstraintType(): void
    {
        try {
            $this->validator->validate('0123456789', new NotBlank());

            static::fail('Expected an unexpected constraint type exception.');
        } catch (\Throwable $exception) {
            static::assertTrue(
                $exception instanceof UnexpectedTypeException || $exception instanceof CustomerException,
                \sprintf('Expected an unexpected constraint type exception, got %s.', $exception::class)
            );
        }
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new CustomerPhoneNumberUniqueValidator($this->customerRepository);
    }

    private function validateValue(EntityRepository $customerRepository, mixed $value, ?string $customerId = null): void
    {
        $validator = new CustomerPhoneNumberUniqueValidator($customerRepository);
        $validator->initialize($this->context);

        $this->setValue($value);

        $validator->validate($value, $this->createConstraint($customerId));
    }

    /**
     * Validates a value that must not reach the repository at all.
     */
    private function validateValueThatIsNotChecked(EntityRepository $customerRepository, mixed $value): void
    {
        $validator = new CustomerPhoneNumberUniqueValidator($customerRepository);
        $validator->initialize($this->context);

        $validator->validate($value, $this->createConstraint());
    }

    private function createConstraint(?string $customerId = null): CustomerPhoneNumberUnique
    {
        $customer = null;

        if ($customerId !== null) {
            $customer = new CustomerEntity();
            $customer->setId($customerId);
        }

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        return new CustomerPhoneNumberUnique($salesChannelContext);
    }

    private function createSearchResult(?string $customerId): IdSearchResult
    {
        $data = [];

        if ($customerId !== null) {
            $data[$customerId] = ['data' => [], 'primaryKey' => $customerId];
        }

        return new IdSearchResult(\count($data), $data, new Criteria(), Context::createDefaultContext());
    }
}

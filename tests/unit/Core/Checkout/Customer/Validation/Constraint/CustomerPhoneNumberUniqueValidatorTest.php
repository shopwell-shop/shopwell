<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
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
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * The validator guards the population the login by sms resolves a number against, so the two halves
 * that matter are the ones a wrong implementation would get wrong: what it does *not* search (a guest,
 * an inactive account, the customer whose number is being saved) and what it reports when the search
 * does find somebody.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerPhoneNumberUniqueValidator::class)]
class CustomerPhoneNumberUniqueValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function ignoredValueProvider(): iterable
    {
        yield 'null unbinds the number' => [null];
        yield 'an array is no number' => [['+4912345']];
        yield 'an empty string' => [''];
        yield 'spaces only' => ['   '];
        yield 'a full width space only' => ["\u{3000}"];
    }

    #[DataProvider('ignoredValueProvider')]
    public function testAValueThatCannotCarryANumberIsNotSearched(mixed $value): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('searchIds');

        $context = $this->expectNoViolation();

        $this->validate($repository, $value, $context);
    }

    public function testItSearchesTheTrimmedNumberAmongActiveNonGuestCustomers(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->with(static::callback(static function (Criteria $criteria): bool {
                static::assertSame(
                    [
                        'phoneNumber' => ['+4912345'],
                        'active' => [true],
                        'guest' => [false],
                    ],
                    self::equalsFiltersOf($criteria),
                    'The number is trimmed before it is searched, and only active customers that are no guests can take it.'
                );

                static::assertSame(1, $criteria->getLimit(), 'One conflict is enough to refuse the number.');

                return true;
            }))
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), Context::createDefaultContext()));

        $context = $this->expectNoViolation();

        $this->validate($repository, '  +4912345  ', $context);
    }

    public function testTheLoggedInCustomerIsNoConflictWithItself(): void
    {
        $customerId = 'customer-of-the-context';

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->with(static::callback(static function (Criteria $criteria) use ($customerId): bool {
                static::assertSame(
                    [$customerId],
                    self::excludedIdsOf($criteria),
                    'Saving the number a customer already owns must not report the customer as its own conflict.'
                );

                return true;
            }))
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), Context::createDefaultContext()));

        $context = $this->expectNoViolation();

        $this->validate($repository, '+4912345', $context, $customerId);
    }

    public function testWithoutALoggedInCustomerNothingIsExcluded(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->with(static::callback(static function (Criteria $criteria): bool {
                static::assertSame([], self::excludedIdsOf($criteria));

                return true;
            }))
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), Context::createDefaultContext()));

        $context = $this->expectNoViolation();

        $this->validate($repository, '+4912345', $context);
    }

    public function testANumberAnotherCustomerOwnsIsViolatedWithTheNamedCause(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('searchIds')
            ->willReturn(IdSearchResult::fromIds(['other-customer'], new Criteria(), Context::createDefaultContext()));

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())
            ->method('setCode')
            ->with(CustomerPhoneNumberUnique::CUSTOMER_PHONE_NUMBER_NOT_UNIQUE)
            ->willReturnSelf();
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            // the storefront translates the template, so the violation carries the snippet key and the
            // named cause separately - a template with the cause in it would be prefixed twice
            ->with(CustomerPhoneNumberUnique::CUSTOMER_PHONE_NUMBER_NOT_UNIQUE_CODE)
            ->willReturn($builder);

        $this->validate($repository, '+4912345', $context);
    }

    public function testAnUnexpectedConstraintTypeIsRejected(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('searchIds');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $validator = new CustomerPhoneNumberUniqueValidator($repository);
        $validator->initialize($context);

        $this->expectException(CustomerException::class);

        $validator->validate('+4912345', new NotBlank());
    }

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    private function validate(
        EntityRepository $customerRepository,
        mixed $value,
        ExecutionContextInterface $context,
        ?string $loggedInCustomerId = null,
    ): void {
        $validator = new CustomerPhoneNumberUniqueValidator($customerRepository);
        $validator->initialize($context);

        $validator->validate($value, $this->createConstraint($loggedInCustomerId));
    }

    private function expectNoViolation(): ExecutionContextInterface&MockObject
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        return $context;
    }

    private function createConstraint(?string $loggedInCustomerId = null): CustomerPhoneNumberUnique
    {
        $customer = null;

        if ($loggedInCustomerId !== null) {
            $customer = new CustomerEntity();
            $customer->setId($loggedInCustomerId);
            $customer->setGuest(false);
        }

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        return new CustomerPhoneNumberUnique($salesChannelContext);
    }

    /**
     * The equals filters of the criteria, keyed by field, so a test reads as the search it describes.
     *
     * @return array<string, list<mixed>>
     */
    private static function equalsFiltersOf(Criteria $criteria): array
    {
        $collected = [];

        foreach (self::flatten($criteria->getFilters()) as $filter) {
            if (!$filter instanceof EqualsFilter) {
                continue;
            }

            $collected[$filter->getField()][] = $filter->getValue();
        }

        return $collected;
    }

    /**
     * @return list<string>
     */
    private static function excludedIdsOf(Criteria $criteria): array
    {
        $excluded = [];

        foreach (self::flatten($criteria->getFilters()) as $filter) {
            if (!$filter instanceof NotFilter) {
                continue;
            }

            foreach (self::flatten($filter->getQueries()) as $query) {
                if ($query instanceof EqualsFilter && $query->getField() === 'id') {
                    $excluded[] = $query->getValue();
                }
            }
        }

        return $excluded;
    }

    /**
     * @param array<array-key, mixed> $filters
     *
     * @return list<object>
     */
    private static function flatten(array $filters): array
    {
        $flat = [];

        foreach ($filters as $filter) {
            // a NotFilter is a MultiFilter as well, but it is one statement and not a group: recursing
            // into it would turn the exclusion into an ordinary equality filter of the search
            if ($filter instanceof NotFilter) {
                $flat[] = $filter;

                continue;
            }

            if ($filter instanceof MultiFilter) {
                $flat = [...$flat, ...self::flatten($filter->getQueries())];

                continue;
            }

            $flat[] = $filter;
        }

        return $flat;
    }
}

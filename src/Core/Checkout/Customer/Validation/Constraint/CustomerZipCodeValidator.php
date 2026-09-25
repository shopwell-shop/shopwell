<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Validation\Constraint;

use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryCollection;
use Shopwell\Core\System\Country\CountryEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidator;

#[Package('checkout')]
class CustomerZipCodeValidator extends ConstraintValidator
{
    /**
     * @internal
     *
     * @param EntityRepository<CountryCollection> $countryRepository
     */
    public function __construct(private readonly EntityRepository $countryRepository)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerZipCode) {
            throw CustomerException::unexpectedType($constraint, CustomerZipCodeValidator::class);
        }

        if ($constraint->getCountryId() === null) {
            return;
        }

        $country = $this->getCountry($constraint->getCountryId());

        if ($country->getPostalCodeRequired()) {
            if ($value === null || $value === '') {
                $this->context->buildViolation($constraint->getMessageRequired())
                    ->setCode(NotBlank::IS_BLANK_ERROR)
                    ->addViolation();

                return;
            }
        }

        if (!$country->getCheckPostalCodePattern() && !$country->getCheckAdvancedPostalCodePattern()) {
            return;
        }

        $pattern = $country->getDefaultPostalCodePattern();

        if ($country->getCheckAdvancedPostalCodePattern()) {
            $pattern = $country->getAdvancedPostalCodePattern();
        }

        if ($pattern === null) {
            return;
        }

        $caseSensitive = $constraint->isCaseSensitiveCheck() ? '' : 'i';

        if (preg_match("/^{$pattern}$/" . $caseSensitive, (string) $value, $matches) === 1) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('{{ iso }}', $this->formatValue($country->getIso()))
            ->setCode(CustomerZipCode::ZIP_CODE_INVALID)
            ->addViolation();
    }

    private function getCountry(string $countryId): CountryEntity
    {
        $country = $this->countryRepository->search(new Criteria([$countryId]), Context::createDefaultContext())->getEntities()->first();
        if (!$country) {
            throw CustomerException::countryNotFound($countryId);
        }

        return $country;
    }
}

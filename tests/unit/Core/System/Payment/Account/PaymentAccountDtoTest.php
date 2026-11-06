<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\PaymentAccountDto;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentAccountDto::class)]
class PaymentAccountDtoTest extends TestCase
{
    public function testRequiresProviderAndNameAtTheRequestBoundary(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $violations = $validator->validate(new PaymentAccountDto());
        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }
        static::assertSame(['providerTechnicalName', 'name'], $paths);
        static::assertCount(0, $validator->validate(new PaymentAccountDto(providerTechnicalName: 'stripe', name: 'Primary account')));
    }
}

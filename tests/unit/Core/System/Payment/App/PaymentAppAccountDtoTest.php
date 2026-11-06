<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppAccountDto;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentAppAccountDto::class)]
class PaymentAppAccountDtoTest extends TestCase
{
    public function testRequiresAnExplicitAccountGrant(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $violations = $validator->validate(new PaymentAppAccountDto());
        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertSame('accountId', $violation->getPropertyPath());
        static::assertCount(0, $validator->validate(new PaymentAppAccountDto(accountId: 'account', enabled: true, position: 10)));
    }
}

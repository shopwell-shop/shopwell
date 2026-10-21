<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Gateway\Api\PaymentMethodChannelSaveDto;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The dto has one rule and one promise: the channel is mandatory, and a body that leaves a field out
 * still deserializes into the default instead of failing before the constraints are looked at.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodChannelSaveDto::class)]
class PaymentMethodChannelSaveDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testAnEmptyBodyIsRejectedBecauseTheChannelIsMandatory(): void
    {
        $violations = $this->validator->validate(new PaymentMethodChannelSaveDto());

        static::assertCount(1, $violations);
        static::assertSame('channelTechnicalName', $violations->get(0)->getPropertyPath());
    }

    public function testAChannelWithoutAnAppIsAccepted(): void
    {
        $violations = $this->validator->validate(new PaymentMethodChannelSaveDto('stripe'));

        static::assertCount(0, $violations);
    }

    public function testAChannelWithAnAppIsAccepted(): void
    {
        $violations = $this->validator->validate(new PaymentMethodChannelSaveDto('stripe', 'app-id'));

        static::assertCount(0, $violations);
    }

    public function testTheFieldsDefaultToNullSoAnOmittedFieldDoesNotFailDeserialization(): void
    {
        $dto = new PaymentMethodChannelSaveDto();

        static::assertNull($dto->channelTechnicalName);
        static::assertNull($dto->paymentAppId);
    }

    public function testTheFieldsAreReadableByName(): void
    {
        $dto = new PaymentMethodChannelSaveDto(channelTechnicalName: 'paypal', paymentAppId: 'app-id');

        static::assertSame('paypal', $dto->channelTechnicalName);
        static::assertSame('app-id', $dto->paymentAppId);
    }

    /**
     * A channel that carries nothing is the same mistake as a missing one.
     *
     * Only the empty string is checked here: `NotBlank` reads a value as blank with `empty()`, so a
     * channel of spaces is not refused by the constraint - the gateway lookup treats an unknown
     * technical name as unknown, and trimming a name that is a technical identifier is not the job
     * of a request body.
     */
    public function testAnEmptyChannelIsRejected(): void
    {
        $violations = $this->validator->validate(new PaymentMethodChannelSaveDto(''));

        static::assertCount(1, $violations);
        static::assertSame('channelTechnicalName', $violations->get(0)->getPropertyPath());
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwellHttpException::class)]
class ShopwellHttpExceptionTest extends TestCase
{
    #[TestDox('placeholders are interpolated from the parameters')]
    public function testMessageInterpolation(): void
    {
        $exception = new TestShopwellHttpException(
            'Could not find {{ entity }} with id "{{ value }}"',
            ['entity' => 'product', 'value' => 'p-1']
        );

        static::assertSame('Could not find product with id "p-1"', $exception->getMessage());
        static::assertSame(['entity' => 'product', 'value' => 'p-1'], $exception->getParameters());
        static::assertSame('product', $exception->getParameter('entity'));
        static::assertNull($exception->getParameter('unknown'));
    }

    #[TestDox('placeholders tolerate inner whitespace')]
    public function testMessageInterpolationWithWhitespace(): void
    {
        $exception = new TestShopwellHttpException('Value {{value}} and {{  value  }}', ['value' => 'x']);

        static::assertSame('Value x and x', $exception->getMessage());
    }

    #[TestDox('array parameters are not interpolated and stay in the placeholder form')]
    public function testArrayParametersAreSkipped(): void
    {
        $exception = new TestShopwellHttpException(
            'Fields {{ fields }} of {{ entity }}',
            ['fields' => ['a', 'b'], 'entity' => 'product']
        );

        static::assertSame('Fields {{ fields }} of product', $exception->getMessage());
    }

    #[TestDox('non-alphabetic characters in parameter keys are stripped for the placeholder match')]
    public function testParameterKeySanitization(): void
    {
        $exception = new TestShopwellHttpException(
            'Value of {{ mykey }}',
            ['my_key1' => 'sanitized']
        );

        static::assertSame('Value of sanitized', $exception->getMessage());
    }

    #[TestDox('getErrors yields the common error data without a trace by default')]
    public function testGetErrors(): void
    {
        $exception = new TestShopwellHttpException('kaputt', ['key' => 'value']);

        $errors = iterator_to_array($exception->getErrors());

        static::assertCount(1, $errors);
        static::assertSame([
            'status' => (string) Response::HTTP_INTERNAL_SERVER_ERROR,
            'code' => 'FRAMEWORK__TEST_ERROR',
            'title' => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
            'detail' => 'kaputt',
            'meta' => [
                'parameters' => ['key' => 'value'],
            ],
        ], $errors[0]);
    }

    #[TestDox('getErrors includes the trace when requested')]
    public function testGetErrorsWithTrace(): void
    {
        $exception = new TestShopwellHttpException('kaputt');

        $errors = iterator_to_array($exception->getErrors(true));

        static::assertArrayHasKey('trace', $errors[0]);
        static::assertIsArray($errors[0]['trace']);
    }
}

/**
 * ShopwellHttpException is abstract; the minimal concrete subclass to exercise the base behaviour.
 *
 * @internal
 */
class TestShopwellHttpException extends ShopwellHttpException
{
    public function getErrorCode(): string
    {
        return 'FRAMEWORK__TEST_ERROR';
    }
}

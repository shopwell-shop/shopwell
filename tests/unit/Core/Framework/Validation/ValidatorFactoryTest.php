<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\DecodedPurchaseStruct;
use Shopwell\Core\Framework\Validation\ValidatorFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ValidatorFactory::class)]
class ValidatorFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $data = [
            'identifier' => 'some-identifier',
            'nextBookingDate' => '2023-10-10',
            'quantity' => 10,
            'sub' => 'some-sub',
        ];

        $result = ValidatorFactory::create($data, DecodedPurchaseStruct::class);

        static::assertInstanceOf(DecodedPurchaseStruct::class, $result);
        static::assertSame('some-identifier', $result->identifier);
        static::assertSame(10, $result->quantity);
    }
}

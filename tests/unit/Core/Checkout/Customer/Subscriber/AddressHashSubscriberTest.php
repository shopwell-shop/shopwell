<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\Subscriber\AddressHashSubscriber;
use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddressHashSubscriber::class)]
class AddressHashSubscriberTest extends TestCase
{
    private AddressHashSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new AddressHashSubscriber();
    }

    #[DataProvider('generateProvider')]
    public function testGenerate(CustomerAddressEntity|OrderAddressEntity $address, string $expectedHash): void
    {
        $event = new EntityLoadedEvent(
            new CustomerAddressDefinition(),
            [$address],
            Context::createDefaultContext()
        );

        $this->subscriber->generateAddressHash($event);

        static::assertSame($expectedHash, $address->getHash());
    }

    public static function generateProvider(): \Generator
    {
        $address = [
            'name' => 'address-first-name address-last-name',
            'zipcode' => 'address-zipcode',
            'city' => 'address-city',
            'company' => 'address-company',
            'department' => 'address-department',
            'title' => 'address-title',
            'street' => 'address-street',
            'additionalAddressLine1' => 'address-additional-address-line-1',
            'additionalAddressLine2' => 'address-additional-address-line-2',
            'countryId' => 'address-country-id',
            'countryStateId' => 'address-country-state-id',
        ];

        yield 'OrderAddressEntity' => [
            (new OrderAddressEntity())->assign($address),
            '86878ee9464270c0dc581558dc32cc8de99b960e45605a0f8a7ae13a89d57dd1',
        ];

        yield 'CustomerAddressEntity' => [
            (new CustomerAddressEntity())->assign($address),
            '86878ee9464270c0dc581558dc32cc8de99b960e45605a0f8a7ae13a89d57dd1',
        ];
    }
}

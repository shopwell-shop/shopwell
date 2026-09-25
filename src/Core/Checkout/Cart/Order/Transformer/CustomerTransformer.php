<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Order\Transformer;

use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @phpstan-type TransformedCustomerArray array{
 *     customerId: string,
 *     email: string,
 *     firstName: string,
 *     lastName: string,
 *     salutationId: string|null,
 *     title: string|null,
 *     vatIds: array<string>|null,
 *     company: string|null,
 *     customerNumber: string,
 *     remoteAddress: string|null,
 *     customFields: array<string, mixed>|null
 * }
 */
#[Package('checkout')]
class CustomerTransformer
{
    /**
     * @return ($useIdAsKey is false ? list<TransformedCustomerArray>: array<string, TransformedCustomerArray>)
     */
    public static function transformCollection(CustomerCollection $customers, bool $useIdAsKey = false): array
    {
        $output = [];
        foreach ($customers as $customer) {
            $output[$customer->getId()] = self::transform($customer);
        }

        if (!$useIdAsKey) {
            return array_values($output);
        }

        return $output;
    }

    /**
     * @return TransformedCustomerArray
     */
    public static function transform(CustomerEntity $customer): array
    {
        return [
            'customerId' => $customer->getId(),
            'email' => $customer->getEmail(),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'salutationId' => $customer->getSalutationId(),
            'title' => $customer->getTitle(),
            'vatIds' => $customer->getVatIds(),
            'company' => $customer->getCompany(),
            'customerNumber' => $customer->getCustomerNumber(),
            'remoteAddress' => $customer->getRemoteAddress(),
            'customFields' => $customer->getCustomFields(),
        ];
    }
}

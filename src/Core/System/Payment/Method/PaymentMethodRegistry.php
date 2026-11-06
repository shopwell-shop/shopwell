<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Method;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;

/**
 * @internal
 */
#[Package('framework')]
final class PaymentMethodRegistry
{
    /**
     * @var array<string, PaymentMethod>
     */
    private array $methods = [];

    /**
     * @param iterable<PaymentMethod> $methods
     */
    public function __construct(iterable $methods)
    {
        foreach ($methods as $method) {
            if (trim($method->technicalName) === '' || isset($this->methods[$method->technicalName])) {
                throw PaymentProviderException::invalidProviderDeclaration('Payment method names must be non-empty and unique: ' . $method->technicalName);
            }
            $this->methods[$method->technicalName] = $method;
        }
    }

    public function get(string $name): PaymentMethod
    {
        return $this->methods[$name] ?? throw PaymentProviderException::unknownMethod($name);
    }

    /**
     * @return array<string, PaymentMethod>
     */
    public function all(): array
    {
        return $this->methods;
    }
}

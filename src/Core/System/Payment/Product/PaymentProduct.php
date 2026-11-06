<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Product;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;

/**
 * A provider's executable offering of one payer-facing payment method.
 * Credentials belong to an account; scenes and handovers belong to the product declaration.
 */
#[Package('framework')]
final readonly class PaymentProduct
{
    public const DEVICE_WAP = 'wap';
    public const DEVICE_WEB = 'web';
    public const DEVICE_APP = 'app';
    public const DEVICE_MINI = 'mini';

    /**
     * @param list<string> $interactions
     * @param list<string> $currencies empty allows all currencies
     * @param list<PaymentScene> $scenes empty allows any explicitly supplied scene
     * @param list<PaymentConfigField> $params
     * @param array<string, bool> $runtimeParams
     * @param list<PaymentCapability>|null $capabilities null inherits the provider's collection capabilities
     */
    public function __construct(
        public string $technicalName,
        public string $providerTechnicalName,
        public string $label,
        public array $interactions,
        public string $method,
        public array $currencies = [],
        public array $scenes = [],
        public array $params = [],
        public array $runtimeParams = [],
        public ?array $capabilities = null,
    ) {
    }

    public function supportsCurrency(string $currency): bool
    {
        return $this->currencies === [] || \in_array(strtoupper($currency), $this->currencies, true);
    }

    /**
     * @return list<string>
     */
    public function devices(): array
    {
        return array_values(array_unique(array_map(static fn (PaymentScene $scene): string => $scene->device, $this->scenes)));
    }

    public function supportsDevice(?string $device): bool
    {
        return $device === null || $device === '' || $this->scenes === [] || \in_array(strtolower($device), $this->devices(), true);
    }

    public function supports(PaymentCapability $capability): bool
    {
        return \in_array($capability, $this->capabilities ?? [], true);
    }

    /**
     * @return list<string>
     */
    public function requiredRuntimeParams(): array
    {
        return array_keys(array_filter($this->runtimeParams));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function validateRuntimeParams(array $params): void
    {
        $missing = [];
        foreach ($this->requiredRuntimeParams() as $name) {
            $value = $params[$name] ?? null;
            if (!\is_scalar($value) || trim((string) $value) === '') {
                $missing[] = $name;
            }
        }
        if ($missing !== []) {
            throw PaymentProviderException::missingRuntimeParams($this->technicalName, $missing);
        }
    }

    /**
     * @param list<PaymentScene> $scenes
     */
    public function withScenes(array $scenes): self
    {
        return new self($this->technicalName, $this->providerTechnicalName, $this->label, $this->interactions, $this->method, $this->currencies, $scenes, $this->params, $this->runtimeParams, $this->capabilities);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product' => $this->technicalName,
            'provider' => $this->providerTechnicalName,
            'method' => $this->method,
            'label' => $this->label,
            'interactions' => $this->interactions,
            'currencies' => $this->currencies,
            'devices' => $this->devices(),
            'scenes' => array_map(static fn (PaymentScene $scene): array => ['name' => $scene->name, 'device' => $scene->device, 'environment' => $scene->environment], $this->scenes),
            'capabilities' => array_map(static fn (PaymentCapability $capability): string => $capability->value, $this->capabilities ?? []),
            'params' => array_map(static fn (PaymentConfigField $field): array => $field->toArray(), $this->params),
            'runtime_params' => $this->runtimeParams,
        ];
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Routing\PaymentContext;

/**
 * Parses client capabilities only after the calling application has been authenticated.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentContextParser
{
    /**
     * @param array<string, mixed> $payload
     */
    public function parse(array $payload): PaymentContext
    {
        $data = $payload['context'] ?? null;
        if (!\is_array($data)) {
            throw PaymentException::missingParameter('context');
        }
        $scene = $data['scene'] ?? null;
        if (!\is_array($scene)) {
            throw PaymentException::missingParameter('context.scene');
        }
        $interactions = $this->strings($data['interactions'] ?? [PaymentInteraction::REDIRECT, PaymentInteraction::QRCODE], 'context.interactions');
        $sdkProducts = $this->strings($data['sdk_products'] ?? [], 'context.sdk_products');
        $runtime = $data['runtime_params'] ?? [];
        if (!\is_array($runtime)) {
            throw PaymentException::missingParameter('context.runtime_params');
        }
        foreach ($runtime as $product => $values) {
            if (!\is_string($product) || !\is_array($values)) {
                throw PaymentException::missingParameter('context.runtime_params');
            }
            foreach ($values as $key => $value) {
                if (!\is_string($key) || !\is_scalar($value)) {
                    throw PaymentException::missingParameter('context.runtime_params');
                }
            }
        }

        $accountRuntime = $data['account_runtime_params'] ?? [];
        if (!\is_array($accountRuntime)) {
            throw PaymentException::missingParameter('context.account_runtime_params');
        }
        foreach ($accountRuntime as $account => $products) {
            if (!\is_string($account) || !\is_array($products)) {
                throw PaymentException::missingParameter('context.account_runtime_params');
            }
            foreach ($products as $product => $values) {
                if (!\is_string($product) || !\is_array($values)) {
                    throw PaymentException::missingParameter('context.account_runtime_params');
                }
                foreach ($values as $key => $value) {
                    if (!\is_string($key) || !\is_scalar($value)) {
                        throw PaymentException::missingParameter('context.account_runtime_params');
                    }
                }
            }
        }

        return new PaymentContext(
            currency: strtoupper($this->requiredString($payload, 'currency')),
            scene: new PaymentScene($this->requiredString($scene, 'name'), $this->requiredString($scene, 'device'), $this->environment($scene)),
            supportedInteractions: $interactions,
            supportedSdkProducts: $sdkProducts,
            runtimeParams: $runtime,
            accountRuntimeParams: $accountRuntime,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!\is_string($value) || trim($value) === '') {
            throw PaymentException::missingParameter($key);
        }

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values, string $key): array
    {
        if (!\is_array($values) || !array_is_list($values)) {
            throw PaymentException::missingParameter($key);
        }
        foreach ($values as $value) {
            if (!\is_string($value) || $value === '') {
                throw PaymentException::missingParameter($key);
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $scene
     */
    private function environment(array $scene): ?string
    {
        return isset($scene['environment']) ? $this->requiredString($scene, 'environment') : null;
    }
}

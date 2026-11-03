<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Config;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\SystemConfig\Util\ConfigReader;

/**
 * Reads payment field declarations with the platform's plugin-config reader. The XML therefore uses
 * the existing config.xsd, locale-aware labels/help texts/options and validation instead of a second
 * payment-only XML dialect.
 *
 * The `credentials` card belongs to the merchant account. A card named by {@see channelCard()} holds
 * the fields of that channel. PHP declarations are appended by PaymentProviderFactory, so an extension
 * can add fields to an existing XML declaration without replacing it.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentConfigReader extends ConfigReader
{
    protected string $xsdFile = __DIR__ . '/../Schema/payment-provider.xsd';

    /**
     * @var array<string, array<array<string, mixed>>>
     */
    private array $documents = [];

    public static function channelCard(string $technicalName): string
    {
        $camelized = preg_replace_callback(
            '/[_-]+([a-zA-Z0-9])/',
            static fn (array $match): string => strtoupper($match[1]),
            $technicalName
        );

        return 'channel' . ucfirst((string) $camelized);
    }

    /**
     * @throws PaymentException
     *
     * @return list<PaymentConfigField>
     */
    public function fields(?string $file, string $cardName): array
    {
        if ($file === null) {
            return [];
        }

        $tabs = $this->documents[$file] ??= $this->read($file);
        $fields = [];

        foreach ($tabs as $tab) {
            foreach ($tab['cards'] ?? [] as $card) {
                if (($card['name'] ?? null) !== $cardName) {
                    continue;
                }

                foreach ($card['elements'] ?? [] as $element) {
                    $fields[] = $this->field($element, $file);
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $element
     *
     * @throws PaymentException
     */
    private function field(array $element, string $file): PaymentConfigField
    {
        $name = $element['name'] ?? null;

        if (!\is_string($name) || $name === '') {
            throw PaymentException::invalidProviderDeclaration(\sprintf('The payment configuration "%s" contains a field without a name.', $file));
        }

        $type = match ($element['type'] ?? PaymentConfigField::TYPE_TEXT) {
            'single-select' => PaymentConfigField::TYPE_SELECT,
            'int', 'float' => PaymentConfigField::TYPE_NUMBER,
            'checkbox' => PaymentConfigField::TYPE_BOOL,
            default => (string) ($element['type'] ?? PaymentConfigField::TYPE_TEXT),
        };

        $options = [];
        foreach ($element['options'] ?? [] as $option) {
            if (\is_array($option) && \is_string($option['id'] ?? null)) {
                $options[$option['id']] = $option['name'] ?? $option['id'];
            }
        }

        $default = $element['defaultValue'] ?? null;

        return new PaymentConfigField(
            name: $name,
            label: $this->localized($element['label'] ?? null),
            type: $type,
            required: (bool) ($element['required'] ?? false),
            secret: (bool) ($element['secret'] ?? false) || $type === PaymentConfigField::TYPE_PASSWORD,
            options: $options,
            default: \is_bool($default) ? ($default ? '1' : '0') : (\is_scalar($default) ? (string) $default : null),
            helpText: $this->localized($element['helpText'] ?? null),
        );
    }

    /**
     * @return string|array<string, string|null>|null
     */
    private function localized(mixed $value): string|array|null
    {
        if (\is_string($value) || \is_array($value)) {
            return $value;
        }

        return null;
    }
}

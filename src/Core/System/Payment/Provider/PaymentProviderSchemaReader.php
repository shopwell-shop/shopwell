<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\XmlReader;
use Shopwell\Core\System\Payment\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * Reads the `payment-provider.xml` of a payment interface into a {@see PaymentProviderDefinition}.
 *
 * It is the counterpart of the `config.xml` of a plugin: the structure of the configuration is declared
 * next to the code of the interface instead of being written into the administration, which is what lets
 * the administration render the credentials of an interface it has never heard of.
 *
 * The file is validated against `payment-provider.xsd`, so an interface with a broken declaration fails
 * at container compile time instead of failing while an operator fills in the form.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentProviderSchemaReader extends XmlReader
{
    protected string $xsdFile = __DIR__ . '/Schema/payment-provider.xsd';

    /**
     * @throws PaymentException
     */
    public function readProvider(string $xmlFile): PaymentProviderDefinition
    {
        $parsed = $this->parseProviderFile($xmlFile);

        $name = $parsed['name'] ?? null;
        if (!\is_string($name)) {
            $name = '';
        }

        $label = $parsed['label'] ?? null;
        if (!\is_string($label)) {
            $label = '';
        }

        /** @var list<array<string, mixed>> $credentials */
        $credentials = $parsed['credentials'] ?? [];
        /** @var list<array<string, mixed>> $channels */
        $channels = $parsed['channels'] ?? [];

        return new PaymentProviderDefinition(
            technicalName: $name,
            label: $label,
            credentials: $this->fieldsOf($credentials, 'The payment interface "' . $name . '"'),
            channels: $this->channelsOf($channels, $name),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseFile(\DOMDocument $xml): array
    {
        \assert($xml->firstChild instanceof \DOMElement);
        $root = $xml->firstChild;

        return [
            'name' => (string) self::getElementChildValueByName($root, 'name', true),
            'label' => (string) self::getElementChildValueByName($root, 'label', true),
            'credentials' => $this->parseFields(self::getFirstChildren($root, 'credentials')),
            'channels' => $this->parseChannels(self::getFirstChildren($root, 'channels')),
        ];
    }

    /**
     * Reads and validates the declaration of an interface.
     *
     * `XmlReader::read()` types everything it returns as an array of arrays, which does not fit an
     * interface declaration: it carries its name and label as plain strings next to the lists of its
     * credentials and channels.
     *
     * @throws PaymentException
     *
     * @return array<string, mixed>
     */
    private function parseProviderFile(string $xmlFile): array
    {
        try {
            $dom = XmlUtils::loadFile($xmlFile, $this->xsdFile);
        } catch (\Exception $exception) {
            throw PaymentException::invalidProviderDeclaration(
                \sprintf('The declaration "%s" is not a valid payment interface: %s', $xmlFile, $exception->getMessage())
            );
        }

        return $this->parseFile($dom);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseFields(?\DOMElement $list): array
    {
        if (!$list instanceof \DOMElement) {
            return [];
        }

        $fields = [];

        foreach (self::getChildByName($list, 'field') as $element) {
            $options = [];

            foreach (self::getChildByName($element, 'option') as $option) {
                $options[(string) $option->getAttribute('value')] = trim((string) $option->nodeValue);
            }

            $default = $element->getAttribute('default');
            $label = self::getElementChildValueByName($element, 'label');
            $helpText = self::getElementChildValueByName($element, 'help-text');

            $fields[] = [
                'name' => (string) $element->getAttribute('name'),
                'label' => $label,
                'helpText' => $helpText,
                'type' => self::validateTextAttribute($element->getAttribute('type'), PaymentConfigField::TYPE_TEXT),
                'required' => self::validateBooleanAttribute($element->getAttribute('required')),
                'secret' => self::validateBooleanAttribute($element->getAttribute('secret')),
                'options' => $options,
                'default' => $default === '' ? null : $default,
            ];
        }

        return $fields;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseChannels(?\DOMElement $list): array
    {
        if (!$list instanceof \DOMElement) {
            return [];
        }

        $channels = [];

        foreach (self::getChildByName($list, 'channel') as $element) {
            $runtimeParams = [];
            $runtimeParamList = self::getFirstChildren($element, 'runtime-params');

            if ($runtimeParamList instanceof \DOMElement) {
                foreach (self::getChildByName($runtimeParamList, 'param') as $param) {
                    $runtimeParams[(string) $param->getAttribute('name')] = self::validateBooleanAttribute($param->getAttribute('required'));
                }
            }

            $channels[] = [
                'name' => (string) $element->getAttribute('name'),
                'label' => self::getElementChildValueByName($element, 'label') ?? '',
                'flow' => self::getElementChildValueByName($element, 'flow') ?? '',
                'currencies' => $this->listOf(self::getElementChildValueByName($element, 'currencies'), true),
                'devices' => $this->listOf(self::getElementChildValueByName($element, 'devices'), false),
                'params' => $this->parseFields(self::getFirstChildren($element, 'params')),
                'runtime-params' => $runtimeParams,
            ];
        }

        return $channels;
    }

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @throws PaymentException
     *
     * @return list<PaymentConfigField>
     */
    private function fieldsOf(array $fields, string $subject): array
    {
        $result = [];
        $seen = [];

        foreach ($fields as $field) {
            $name = (string) $field['name'];

            if ($name === '') {
                throw PaymentException::invalidProviderDeclaration($subject . ' declares a configuration field without a name.');
            }

            if (isset($seen[$name])) {
                throw PaymentException::invalidProviderDeclaration(\sprintf('%s declares the configuration field "%s" twice.', $subject, $name));
            }

            $seen[$name] = true;

            /** @var array<string, string> $options */
            $options = $field['options'];
            $default = $field['default'];
            $label = $field['label'];

            $result[] = new PaymentConfigField(
                name: $name,
                label: \is_string($label) && $label !== '' ? $label : null,
                type: (string) $field['type'],
                required: (bool) $field['required'],
                secret: (bool) $field['secret'],
                options: $options,
                default: \is_string($default) ? $default : null,
                helpText: \is_string($field['helpText']) && $field['helpText'] !== '' ? $field['helpText'] : null,
            );
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $channels
     *
     * @throws PaymentException
     *
     * @return list<PaymentChannel>
     */
    private function channelsOf(array $channels, string $providerName): array
    {
        $result = [];
        $seen = [];

        foreach ($channels as $channel) {
            $name = (string) $channel['name'];

            if ($name === '') {
                throw PaymentException::invalidProviderDeclaration(\sprintf('The payment interface "%s" declares a channel without a name.', $providerName));
            }

            if (isset($seen[$name])) {
                throw PaymentException::invalidProviderDeclaration(\sprintf('The channel "%s" is declared twice.', $name));
            }

            $seen[$name] = true;

            $flow = PaymentFlow::tryFrom((string) $channel['flow']);

            if (!$flow instanceof PaymentFlow) {
                throw PaymentException::invalidProviderDeclaration(\sprintf('The channel "%s" declares the unknown flow "%s".', $name, (string) $channel['flow']));
            }

            /** @var list<string> $currencies */
            $currencies = $channel['currencies'];
            /** @var list<string> $devices */
            $devices = $channel['devices'];
            /** @var array<string, bool> $runtimeParams */
            $runtimeParams = $channel['runtime-params'];

            $result[] = new PaymentChannel(
                technicalName: $name,
                providerName: $providerName,
                label: (string) $channel['label'],
                flow: $flow,
                currencies: $currencies,
                devices: $devices,
                params: $this->fieldsOf($channel['params'], \sprintf('The channel "%s"', $name)),
                runtimeParams: $runtimeParams,
            );
        }

        return $result;
    }

    /**
     * A comma separated list of a channel, e.g. `CNY,USD`. An empty element means "every value".
     *
     * @return list<string>
     */
    private function listOf(?string $value, bool $uppercase): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $list = [];

        foreach (explode(',', $value) as $item) {
            $item = trim($item);

            if ($item === '') {
                continue;
            }

            $list[] = $uppercase ? strtoupper($item) : strtolower($item);
        }

        return array_values(array_unique($list));
    }
}

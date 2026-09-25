<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Flow\Action\Xml;

use Shopwell\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Config extends XmlElement
{
    /**
     * @var list<InputField>
     */
    protected array $config;

    /**
     * @return list<InputField>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->getElementsByTagName('input-field') as $parameter) {
            $values[] = InputField::fromXml($parameter);
        }

        return ['config' => $values];
    }
}

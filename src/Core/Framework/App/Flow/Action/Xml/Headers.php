<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Flow\Action\Xml;

use Shopwell\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Headers extends XmlElement
{
    /**
     * @var list<Parameter>
     */
    protected array $parameters;

    /**
     * @return list<Parameter>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->getElementsByTagName('parameter') as $parameters) {
            $values[] = Parameter::fromXml($parameters);
        }

        return ['parameters' => $values];
    }
}

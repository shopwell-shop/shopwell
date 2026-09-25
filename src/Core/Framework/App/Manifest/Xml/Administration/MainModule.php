<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Manifest\Xml\Administration;

use Shopwell\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopwell\Core\Framework\App\Manifest\XmlParserUtils;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class MainModule extends XmlElement
{
    protected string $source;

    public function getSource(): string
    {
        return $this->source;
    }

    protected static function parse(\DOMElement $element): array
    {
        return XmlParserUtils::parseAttributes($element);
    }
}

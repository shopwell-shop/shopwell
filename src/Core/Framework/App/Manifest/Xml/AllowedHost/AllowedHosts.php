<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Manifest\Xml\AllowedHost;

use Shopwell\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopwell\Core\Framework\App\Manifest\XmlParserUtils;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AllowedHosts extends XmlElement
{
    /**
     * @var list<string>
     */
    protected array $allowedHosts;

    /**
     * @return list<string>
     */
    public function getHosts(): array
    {
        return $this->allowedHosts;
    }

    protected static function parse(\DOMElement $element): array
    {
        return ['allowedHosts' => XmlParserUtils::parseChildrenAsList($element)];
    }
}

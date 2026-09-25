<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * Represents the XML field element
 *
 * admin-ui > entity > detail > tabs > tab > card > field
 *
 * @internal
 */
#[Package('framework')]
final class CardField extends ConfigXmlElement
{
    protected string $ref;

    public function getRef(): string
    {
        return $this->ref;
    }

    protected static function parse(\DOMElement $element): array
    {
        return ['ref' => XmlUtils::phpize($element->getAttribute('ref'))];
    }
}

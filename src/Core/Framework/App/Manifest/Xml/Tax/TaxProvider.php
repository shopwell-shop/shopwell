<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Manifest\Xml\Tax;

use Shopwell\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopwell\Core\Framework\App\Manifest\XmlParserUtils;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class TaxProvider extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'identifier',
        'name',
        'processUrl',
        'priority',
    ];

    protected string $identifier;

    protected string $name;

    protected string $processUrl;

    protected int $priority;

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProcessUrl(): string
    {
        return $this->processUrl;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    protected static function parse(\DOMElement $element): array
    {
        return XmlParserUtils::parseChildren($element);
    }
}

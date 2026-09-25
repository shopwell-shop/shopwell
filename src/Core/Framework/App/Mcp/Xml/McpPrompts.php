<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Mcp\Xml;

use Shopwell\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class McpPrompts extends XmlElement
{
    /**
     * @var list<McpPrompt>
     */
    protected array $prompts;

    /**
     * @return list<McpPrompt>
     */
    public function getPrompts(): array
    {
        return $this->prompts;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function parse(\DOMElement $element): array
    {
        $prompts = [];
        foreach ($element->getElementsByTagName('mcp-prompt') as $prompt) {
            $prompts[] = McpPrompt::fromXml($prompt);
        }

        return ['prompts' => $prompts];
    }
}

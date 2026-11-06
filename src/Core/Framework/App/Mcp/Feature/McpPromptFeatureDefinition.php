<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Mcp\Feature;

use Shopwell\Core\Framework\App\Feature\AppFeatureConfig;
use Shopwell\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopwell\Core\Framework\App\Feature\TranslatedString;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Manifest\XmlParserUtils;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @extends AppFeatureDefinition<McpPromptConfig>
 *
 * @phpstan-import-type McpPromptPayload from McpPromptConfig
 */
#[Package('framework')]
class McpPromptFeatureDefinition extends AppFeatureDefinition
{
    public const TYPE = 'mcp_prompt';

    private const FILE = 'Resources/mcp.xml';

    private const XSD_FILE = __DIR__ . '/../Schema/mcp-1.0.xsd';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getConfigClass(): string
    {
        return McpPromptConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        if (!$appFilesystem->has(self::FILE)) {
            return [];
        }

        $doc = XmlParserUtils::loadFile($appFilesystem->path(self::FILE), self::XSD_FILE);

        $configs = [];
        foreach ($doc->getElementsByTagName('mcp-prompt') as $element) {
            $configs[] = new McpPromptConfig(
                $element->getAttribute('name'),
                $element->getAttribute('url'),
                new TranslatedString(XmlParserUtils::ensureTranslationForLocale(
                    XmlParserUtils::parseTranslations($element, 'label'),
                    $defaultLocale,
                )),
                new TranslatedString(XmlParserUtils::ensureTranslationForLocale(
                    XmlParserUtils::parseTranslations($element, 'description'),
                    $defaultLocale,
                )),
            );
        }

        return $configs;
    }

    /**
     * @return McpPromptPayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return $declared->toArray();
    }

    /**
     * @param McpPromptPayload $payload
     */
    public function fromPayload(array $payload): McpPromptConfig
    {
        return new McpPromptConfig(
            $payload['name'],
            $payload['url'],
            new TranslatedString($payload['label'] ?? []),
            new TranslatedString($payload['description'] ?? []),
        );
    }
}

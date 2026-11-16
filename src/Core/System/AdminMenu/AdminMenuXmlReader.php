<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\XmlReader;

/**
 * @internal
 *
 * @extends XmlReader<MenuDefinition>
 *
 * @phpstan-type MenuTranslation array{title: string, customFields?: array<string, mixed>}
 * @phpstan-type MenuDefinition array{internalKey: string, type: string, title: string, icon: ?string, color: ?string, parent: ?string, position: ?int, privilege: ?string, linkTarget: ?string, groupName: ?string, translations: array<string, MenuTranslation>}
 */
#[Package('fundamentals@framework')]
class AdminMenuXmlReader extends XmlReader
{
    public const SCHEMA_FILE = __DIR__ . '/Schema/admin-menu.xsd';

    protected string $xsdFile = self::SCHEMA_FILE;

    /**
     * @return list<MenuDefinition>
     */
    public function read(string $xmlFile): array
    {
        try {
            return array_values(parent::read($xmlFile));
        } catch (AdminMenuException $exception) {
            throw AdminMenuException::invalidDefinition($xmlFile, $exception->getMessage());
        }
    }

    /**
     * @return list<MenuDefinition>
     */
    protected function parseFile(\DOMDocument $xml): array
    {
        \assert($xml->documentElement instanceof \DOMElement);

        return $this->readMenus($xml->documentElement);
    }

    /**
     * @return list<MenuDefinition>
     */
    private function readMenus(\DOMElement $container, ?string $parent = null): array
    {
        $definitions = [];
        foreach (self::getAllChildren($container) as $menu) {
            if ($menu->tagName !== 'menu') {
                continue;
            }

            $key = $menu->getAttribute('id');
            if ($parent !== null && $menu->hasAttribute('parent')) {
                throw AdminMenuException::invalidDefinition($key, 'Nested menus inherit their parent; do not declare a parent attribute.');
            }

            $type = $menu->getAttribute('type') ?: AdminMenuItemDefinition::TYPE_MENU;
            $link = $menu->hasAttribute('link') ? $menu->getAttribute('link') : null;
            if (($type === AdminMenuItemDefinition::TYPE_LINK) !== ($link !== null)) {
                throw AdminMenuException::invalidDefinition($key, 'A link target is required for link menus and forbidden for other types.');
            }

            $translations = $this->readTranslations($menu, $key);
            $definitions[] = [
                'internalKey' => $key,
                'type' => $type,
                'title' => ($translations['en-GB'] ?? reset($translations))['title'],
                'icon' => $menu->hasAttribute('icon') ? $menu->getAttribute('icon') : null,
                'color' => $menu->hasAttribute('color') ? $menu->getAttribute('color') : null,
                'parent' => $parent ?? ($menu->hasAttribute('parent') ? $menu->getAttribute('parent') : null),
                'position' => $menu->hasAttribute('position') ? (int) $menu->getAttribute('position') : null,
                'privilege' => $menu->hasAttribute('privilege') ? $menu->getAttribute('privilege') : null,
                'linkTarget' => $link,
                'groupName' => $menu->hasAttribute('group') ? $menu->getAttribute('group') : null,
                'translations' => $translations,
            ];
            $definitions = [...$definitions, ...$this->readMenus($menu, $key)];
        }

        return $definitions;
    }

    /**
     * @return non-empty-array<string, MenuTranslation>
     */
    private function readTranslations(\DOMElement $menu, string $key): array
    {
        $translations = [];
        foreach (self::getAllChildren($menu) as $element) {
            if ($element->tagName !== 'title') {
                continue;
            }

            $locale = $element->getAttribute('lang');
            if (isset($translations[$locale])) {
                throw AdminMenuException::invalidDefinition($key, \sprintf('Duplicate title for locale "%s".', $locale));
            }
            $translations[$locale] = ['title' => trim($element->textContent)];
        }

        if ($translations === []) {
            throw AdminMenuException::invalidDefinition($key, 'At least one translated title is required.');
        }

        foreach (self::getAllChildren($menu) as $element) {
            if ($element->tagName !== 'custom-fields') {
                continue;
            }

            $locale = $element->getAttribute('lang');
            if (!isset($translations[$locale])) {
                throw AdminMenuException::invalidDefinition($key, \sprintf('Custom fields require a title for locale "%s".', $locale));
            }
            if (isset($translations[$locale]['customFields'])) {
                throw AdminMenuException::invalidDefinition($key, \sprintf('Duplicate custom-fields for locale "%s".', $locale));
            }

            $translations[$locale]['customFields'] = $this->readCustomFields($element, $key);
        }

        return $translations;
    }

    /**
     * @return array<string, mixed>
     */
    private function readCustomFields(\DOMElement $element, string $key): array
    {
        $values = [];
        foreach (self::getAllChildren($element) as $field) {
            $name = $field->getAttribute('name');
            if (\array_key_exists($name, $values)) {
                throw AdminMenuException::invalidDefinition($key, \sprintf('Duplicate custom field "%s".', $name));
            }

            try {
                $values[$name] = $field->getAttribute('type') === 'json'
                    ? json_decode($field->textContent, associative: true, flags: \JSON_THROW_ON_ERROR)
                    : $field->textContent;
            } catch (\JsonException) {
                throw AdminMenuException::invalidDefinition($key, \sprintf('Custom field "%s" contains invalid JSON.', $name));
            }
        }

        return $values;
    }
}

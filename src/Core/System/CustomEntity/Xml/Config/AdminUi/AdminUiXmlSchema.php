<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomEntity\Xml\Config\AdminUi;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\XmlParser;
use Shopwell\Core\System\CustomEntity\CustomEntityException;
use Shopwell\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\AdminUi;

/**
 * @internal
 */
#[Package('framework')]
final readonly class AdminUiXmlSchema
{
    public const FILENAME = 'admin-ui.xml';

    public const XSD_FILEPATH = __DIR__ . '/admin-ui-1.0.xsd';

    private function __construct(
        private AdminUi $adminUi
    ) {
    }

    public static function createFromXmlFile(string $xmlFilePath): self
    {
        try {
            $doc = XmlParser::loadFile($xmlFilePath, self::XSD_FILEPATH);
        } catch (\Exception $e) {
            throw CustomEntityException::xmlParsingException($xmlFilePath, $e->getMessage());
        }

        /** @var \DOMElement $domItem */
        $domItem = $doc->getElementsByTagName('admin-ui')->item(0);

        return new self(AdminUi::fromXml($domItem));
    }

    public function getAdminUi(): AdminUi
    {
        return $this->adminUi;
    }
}

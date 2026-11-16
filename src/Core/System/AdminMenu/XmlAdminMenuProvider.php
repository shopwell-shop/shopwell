<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * XML is validated and compiled into these arguments when the container is built.
 * Serving navigation therefore needs neither filesystem access nor XML parsing.
 *
 * @internal
 *
 * @phpstan-import-type MenuDefinition from AdminMenuXmlReader
 */
#[Package('fundamentals@framework')]
class XmlAdminMenuProvider implements AdminMenuDefinitionProviderInterface
{
    /**
     * @param list<MenuDefinition> $definitions
     */
    public function __construct(
        private array $definitions,
        private readonly ?string $pluginTechnicalName = null,
        private readonly ?string $sourceFile = null,
        private readonly AdminMenuXmlReader $reader = new AdminMenuXmlReader(),
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Plugin updates may replace XML before the current container is rebuilt.
     * Only lifecycle synchronization calls this; navigation uses compiled/cached rows.
     */
    public function refresh(): void
    {
        if ($this->sourceFile === null) {
            return;
        }
        $this->definitions = $this->filesystem->exists($this->sourceFile) ? $this->reader->read($this->sourceFile) : [];
    }

    public function getPluginTechnicalName(): ?string
    {
        return $this->pluginTechnicalName;
    }

    public function getDefinitions(): array
    {
        return array_map(static fn (array $definition): AdminMenuDefinitionStruct => new AdminMenuDefinitionStruct(...$definition), $this->definitions);
    }
}

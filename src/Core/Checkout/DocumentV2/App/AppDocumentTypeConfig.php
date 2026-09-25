<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\App;

use Shopwell\Core\Framework\App\Feature\AppFeatureConfig;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class AppDocumentTypeConfig implements AppFeatureConfig
{
    /**
     * @param list<string> $formats
     * @param array<string, string> $label
     * @param array<string, scalar> $config
     */
    public function __construct(
        private string $identifier,
        private array $formats,
        private array $label,
        private array $config,
    ) {
    }

    public function getName(): string
    {
        return $this->identifier;
    }

    /**
     * @return list<string>
     */
    public function getFormats(): array
    {
        return $this->formats;
    }

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    /**
     * @return array<string, scalar>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}

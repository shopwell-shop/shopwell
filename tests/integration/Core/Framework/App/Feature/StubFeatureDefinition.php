<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\Feature;

use Shopwell\Core\Framework\App\Feature\AppFeatureConfig;
use Shopwell\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @extends AppFeatureDefinition<AppFeatureConfig>
 */
final class StubFeatureDefinition extends AppFeatureDefinition
{
    public function getType(): string
    {
        return 'stub_feature';
    }

    public function getConfigClass(): string
    {
        return StubFeatureConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        return [];
    }

    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        if (!$declared instanceof StubFeatureConfig) {
            throw new \InvalidArgumentException('StubFeatureDefinition only handles StubFeatureConfig');
        }

        return ['name' => $declared->name, 'value' => $declared->value];
    }

    public function fromPayload(array $payload): AppFeatureConfig
    {
        return new StubFeatureConfig((string) $payload['name'], (string) $payload['value']);
    }
}

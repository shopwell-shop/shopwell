<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Service\TranslationConfigLoader;

/**
 * @internal
 */
#[Package('discovery')]
class TestableTranslationConfigLoader extends TranslationConfigLoader
{
    private string $relativeConfigurationPath = __DIR__ . '/fixtures';

    private string $configFileName = 'translation.yaml';

    public function getParentRelativeConfigurationPath(): string
    {
        return parent::getRelativeConfigurationPath();
    }

    public function getParentConfigFilename(): string
    {
        return parent::getConfigFilename();
    }

    public function setRelativeConfigurationPath(string $relativeConfigurationPath): void
    {
        $this->relativeConfigurationPath = $relativeConfigurationPath;
    }

    public function setConfigFileName(string $configFileName): void
    {
        $this->configFileName = $configFileName;
    }

    protected function getRelativeConfigurationPath(): string
    {
        return $this->relativeConfigurationPath;
    }

    protected function getConfigFilename(): string
    {
        return $this->configFileName;
    }
}

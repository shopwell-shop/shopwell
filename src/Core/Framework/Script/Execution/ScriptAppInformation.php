<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ScriptAppInformation
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $version,
        private readonly string $integrationId,
    ) {
    }

    public function getAppId(): string
    {
        return $this->id;
    }

    public function getAppName(): string
    {
        return $this->name;
    }

    public function getAppVersion(): string
    {
        return $this->version;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }
}

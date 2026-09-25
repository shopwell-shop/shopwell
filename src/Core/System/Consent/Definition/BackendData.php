<?php declare(strict_types=1);

namespace Shopwell\Core\System\Consent\Definition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Consent\ConsentDefinition;
use Shopwell\Core\System\Consent\ConsentScope;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('data-services')]
class BackendData implements ConsentDefinition
{
    public const NAME = 'backend_data';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getScopeName(): string
    {
        return ConsentScope\System::NAME;
    }

    public function getSince(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2025-12-12');
    }

    public function getRequiredPermissions(): array
    {
        return ['system.system_config'];
    }

    public function getLatestRevision(): ?string
    {
        return null;
    }
}

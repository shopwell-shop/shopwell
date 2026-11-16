<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization;

use Shopwell\Core\Framework\Log\Package;

/**
 * Identities for the initial organization assignments seeded by the core migration.
 * These records remain editable; they do not imply a reporting line or approval authority.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
final class OrganizationDefaults
{
    public const ORGANIZATION = 'e63dfb115a9f4b7a95d43f2c161b47a5';

    public const UNIT = '58a15e54560d448fbd3e0303f6b5062a';

    public const POSITION = '97d308ba1cd74837ae073ae4a7036d38';
}

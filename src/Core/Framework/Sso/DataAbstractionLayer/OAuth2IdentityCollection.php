<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OAuth2IdentityEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class OAuth2IdentityCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'oauth2_identity_collection';
    }

    protected function getExpectedClass(): string
    {
        return OAuth2IdentityEntity::class;
    }
}

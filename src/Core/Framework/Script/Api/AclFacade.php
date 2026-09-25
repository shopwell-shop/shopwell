<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * The `acl` service allows you to check if your app has been granted the specified privilege.
 *
 * @script-service miscellaneous
 */
#[Package('framework')]
class AclFacade
{
    public function __construct(private readonly Context $appContext)
    {
    }

    /**
     * The `can()` method allows you to check if your app has been granted the specified privilege.
     *
     * @param string $privilege The privilege you wish to check
     *
     * @return bool Returns `true` if the privilege has been granted, `false` otherwise.
     *
     * @example /acl/check.twig Check for the `product:read` permission and query a product if the permission is granted.
     */
    public function can(string $privilege): bool
    {
        return $this->appContext->isAllowed($privilege);
    }
}

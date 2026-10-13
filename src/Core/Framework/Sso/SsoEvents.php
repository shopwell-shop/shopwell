<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class SsoEvents
{
    /**
     * Dispatched after a login through a configured OAuth2 channel resolved to a logged-in
     * customer or admin user. The session is already established and the event data is read-only.
     */
    final public const OAUTH2_LOGIN_SUCCEEDED = 'sso.oauth2.login.succeeded';
}

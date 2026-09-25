<?php declare(strict_types=1);

namespace Shopwell\Core\System\Consent\ConsentScope;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Consent\ConsentException;
use Shopwell\Core\System\Consent\ConsentScope;

/**
 * @internal
 */
#[Package('data-services')]
class System implements ConsentScope
{
    public const NAME = 'system';

    public function getName(): string
    {
        return self::NAME;
    }

    public function resolveIdentifier(Context $context): string
    {
        return self::NAME;
    }

    /**
     * This consent is scoped to the system, but a particular admin user performed the action
     */
    public function resolveActorIdentifier(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        $userId = $source->getUserId();
        if (!$userId) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        return $userId;
    }
}

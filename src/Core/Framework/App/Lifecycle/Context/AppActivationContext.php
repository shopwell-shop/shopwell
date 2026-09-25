<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Context;

use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final readonly class AppActivationContext
{
    public function __construct(
        public AppEntity $app,
        public Context $context,
    ) {
    }
}

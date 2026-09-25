<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Script\Execution\Awareness;

use Shopwell\Core\Framework\Log\Package;

/**
 * AppSpecific hooks are only executed for the given AppId, e.g. app lifecycle hooks
 *
 * @internal
 */
#[Package('framework')]
interface AppSpecificHook
{
    public function getAppId(): string;
}

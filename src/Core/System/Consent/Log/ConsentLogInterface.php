<?php declare(strict_types=1);

namespace Shopwell\Core\System\Consent\Log;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Consent\ConsentStatus;

#[Package('data-services')]
interface ConsentLogInterface
{
    public function log(ConsentStatus $action, string $consentName, ?string $identifier, string $actor): void;
}

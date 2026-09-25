<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Payload;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface SourcedPayloadInterface extends \JsonSerializable
{
    public function setSource(Source $source): void;
}

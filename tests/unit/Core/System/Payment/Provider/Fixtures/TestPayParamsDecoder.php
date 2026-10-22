<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Provider\PayParamsDecoderInterface;

/**
 * A pay params decoder that decodes nothing.
 *
 * @internal
 */
#[Package('framework')]
final class TestPayParamsDecoder implements PayParamsDecoderInterface
{
    public function payParamsFromResponse(array $response): ?PayParams
    {
        return null;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Password\LegacyEncoder;

use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
interface LegacyEncoderInterface
{
    public function getName(): string;

    public function isPasswordValid(#[\SensitiveParameter] string $password, string $hash): bool;
}

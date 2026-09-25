<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Captcha\BasicCaptcha;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('discovery')]
class BasicCaptchaImage extends Struct
{
    public function __construct(
        private readonly string $code,
        private readonly string $imageBase64
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function imageBase64(): string
    {
        return $this->imageBase64;
    }
}

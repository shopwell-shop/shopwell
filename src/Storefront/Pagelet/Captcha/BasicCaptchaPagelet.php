<?php declare(strict_types=1);

namespace Shopwell\Storefront\Pagelet\Captcha;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Framework\Captcha\BasicCaptcha\BasicCaptchaImage;
use Shopwell\Storefront\Pagelet\Pagelet;

#[Package('discovery')]
class BasicCaptchaPagelet extends Pagelet
{
    protected BasicCaptchaImage $captcha;

    public function setCaptcha(BasicCaptchaImage $captcha): void
    {
        $this->captcha = $captcha;
    }

    public function getCaptcha(): BasicCaptchaImage
    {
        return $this->captcha;
    }
}

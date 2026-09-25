---
title: Integrate Google ReCaptcha Server side validation
issue: NEXT-14133
---
# Storefront
* Changed `\Shopwell\Storefront\Framework\Captcha\AbstractCaptcha::supports` method to provide a common method for the implementation captchas
* Added a new service `shopwell.captcha.client` which is an instance of `GuzzleHttp\Client`
* Changed `\Shopwell\Storefront\Framework\Captcha\GoogleReCaptchaV2::isValid` to validate google reCaptcha v2 server side
* Changed `\Shopwell\Storefront\Framework\Captcha\GoogleReCaptchaV3::isValid` to validate google reCaptcha v3 server side

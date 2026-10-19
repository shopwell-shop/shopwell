<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Template\Audit;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * The body of the default language, the only body this module submits and renders.
 *
 * It is a reader of its own because the audit and the renderer must look at the same body: a
 * template whose reported body differs from the rendered one is rejected by the provider at send
 * time, and the operator would have no way to see why.
 *
 * A caller that loaded the translations association gets the row of the default language, a caller
 * that did not gets the same body from the translated value of the entity - the data abstraction
 * layer keeps the body of the default language there, next to the body of the context language.
 *
 * @internal
 */
#[Package('framework')]
final class SmsTemplateBodyReader
{
    public function readDefaultContent(SmsTemplateEntity $template): string
    {
        $translation = ($template->translations ?? [])[Defaults::LANGUAGE_SYSTEM] ?? null;
        $content = $translation?->get('content');

        if (\is_string($content)) {
            return $content;
        }

        $translated = $template->getTranslated()['content'] ?? null;

        if (\is_string($translated)) {
            return $translated;
        }

        return $template->content ?? '';
    }
}

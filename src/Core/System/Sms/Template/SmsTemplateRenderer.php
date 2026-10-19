<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Template;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;

/**
 * Renders the body of a template of the free text mode, the providers that require a reported
 * template never reach this class.
 *
 * It deliberately reads the body of the language the message goes out in rather than the body the
 * audit reports: a template based gateway sends a code and its parameters, so the body of the
 * default language never travels to the provider and cannot conflict with this one.
 *
 * @internal
 */
#[Package('framework')]
final class SmsTemplateRenderer
{
    private const PLACEHOLDER = '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/';

    /**
     * @param array<string, mixed> $params
     *
     * @throws SmsException
     */
    public function render(SmsTemplateEntity $template, array $params): string
    {
        $templateRef = $template->technicalName;
        $content = $this->content($template);

        if ($content === null || trim($content) === '') {
            throw SmsException::templateContentMissing($templateRef);
        }

        $rendered = preg_replace_callback(
            self::PLACEHOLDER,
            static function (array $matches) use ($params, $templateRef): string {
                $name = $matches[1];

                if (!\array_key_exists($name, $params)) {
                    throw SmsException::invalidTemplateParams($templateRef, [\sprintf('the content refers to "%s" which was not passed', $name)]);
                }

                return (string) $params[$name];
            },
            $content
        ) ?? $content;

        $maxLength = $template->maxLength;
        $length = mb_strlen($rendered);

        if ($maxLength !== null && $length > $maxLength) {
            throw SmsException::messageTooLong($templateRef, $length, $maxLength);
        }

        return $rendered;
    }

    /**
     * The body of the language the message goes out in, and the one of the default language for a
     * language that has no body of its own yet - the translated value of the entity is the one of the
     * default language, the property holds the language of the context only.
     */
    private function content(SmsTemplateEntity $template): ?string
    {
        $content = $template->content;

        if ($content !== null && trim($content) !== '') {
            return $content;
        }

        $defaultContent = $template->getTranslated()['content'] ?? null;

        return \is_string($defaultContent) && trim($defaultContent) !== '' ? $defaultContent : null;
    }
}

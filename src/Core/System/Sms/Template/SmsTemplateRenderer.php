<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Template;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\Audit\SmsTemplateBodyReader;

/**
 * Renders the body of a template of the free text mode, the providers that require a reported
 * template never reach this class.
 *
 * The rendered body is the body of the default language, whatever language the context carries: the
 * body that was reported at the provider is the body that is sent, so the two cannot differ.
 *
 * @internal
 */
#[Package('framework')]
final class SmsTemplateRenderer
{
    private const PLACEHOLDER = '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/';

    public function __construct(private readonly SmsTemplateBodyReader $bodyReader)
    {
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws SmsException
     */
    public function render(SmsTemplateEntity $template, array $params): string
    {
        $templateRef = $template->technicalName;
        $content = $this->bodyReader->readDefaultContent($template);

        if (trim($content) === '') {
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
}

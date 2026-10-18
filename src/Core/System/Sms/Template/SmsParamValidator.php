<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Template;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;

/**
 * Checks the parameters of a message against the contract of the template before a provider is
 * called.
 *
 * The contract is what makes a wrong parameter a failure before the provider answers: a provider
 * rejects a message with a wrong parameter as an invalid request, and a rejection is what the retry
 * policy of the queue must not retry - see the failure classification of the component.
 *
 * @internal
 */
#[Package('framework')]
final class SmsParamValidator
{
    /**
     * @param array<string, mixed> $params
     *
     * @throws SmsException
     */
    public function validate(SmsTemplateEntity $template, array $params): void
    {
        $violations = [];

        foreach ($template->paramsSchema ?? [] as $definition) {
            $name = (string) ($definition['name'] ?? '');

            if ($name === '') {
                continue;
            }

            if (!\array_key_exists($name, $params)) {
                if (($definition['required'] ?? false) === true) {
                    $violations[] = \sprintf('"%s" is required', $name);
                }

                continue;
            }

            $pattern = $definition['pattern'] ?? null;

            if (\is_string($pattern) && $pattern !== '' && !$this->matches($pattern, $params[$name])) {
                $violations[] = \sprintf('"%s" does not match %s', $name, $pattern);
            }
        }

        if ($violations !== []) {
            throw SmsException::invalidTemplateParams($template->technicalName, $violations);
        }
    }

    private function matches(string $pattern, mixed $value): bool
    {
        if (\is_int($value) || \is_float($value)) {
            $value = (string) $value;
        }

        if (!\is_string($value)) {
            return false;
        }

        return preg_match('~' . str_replace('~', '\\~', $pattern) . '~', $value) === 1;
    }
}

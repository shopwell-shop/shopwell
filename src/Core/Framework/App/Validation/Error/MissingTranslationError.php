<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation\Error;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\App\Validation\ManifestValidatorTest
 */
#[Package('framework')]
class MissingTranslationError implements Error
{
    private readonly string $message;

    /**
     * @param array<string, array<string>> $missingTranslations
     */
    public function __construct(
        string $xmlElementClass,
        array $missingTranslations
    ) {
        $path = explode('\\', $xmlElementClass);
        $xmlClassName = array_pop($path);

        $validations = [];
        foreach ($missingTranslations as $field => $missingTranslation) {
            $validations[] = $field . ': ' . implode(', ', $missingTranslation);
        }

        $this->message = \sprintf(
            "Missing translations for \"%s\":\n- %s",
            $xmlClassName,
            implode("\n- ", $validations)
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\Validation;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Validation\AppValidator;
use Shopwell\Core\Framework\App\Validation\Error\AppNameError;
use Shopwell\Core\Framework\App\Validation\Error\ConfigurationError;
use Shopwell\Core\Framework\App\Validation\Error\Error;
use Shopwell\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopwell\Core\Framework\App\Validation\Error\MissingTranslationError;
use Shopwell\Core\Framework\App\Validation\Error\NotHookableError;
use Shopwell\Core\Framework\App\Validation\Error\Severity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class AppValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AppValidator $appValidator;

    protected function setUp(): void
    {
        $this->appValidator = static::getContainer()->get(AppValidator::class);
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $result = $this->appValidator->validate($manifest, Context::createDefaultContext());

        static::assertTrue($result->isOk(), \sprintf(
            'No error expected, got: %s',
            implode(', ', array_map(static fn ($error) => $error->getMessage(), $result->errors ?? []))
        ));
    }

    public function testValidateInvalidManifest(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/invalidManifest/manifest.xml');

        $result = $this->appValidator->validate($manifest, Context::createDefaultContext());

        static::assertNotNull($result->errors);
        static::assertSame(
            [
                [MissingTranslationError::class, MissingTranslationError::CODE, Severity::Notice],
                [AppNameError::class, AppNameError::CODE, Severity::Fail],
                [ConfigurationError::class, AppException::INVALID_CONFIGURATION, Severity::Fail],
                [NotHookableError::class, NotHookableError::CODE, Severity::Notice],
                [MissingPermissionError::class, MissingPermissionError::CODE, Severity::Fail],
            ],
            array_map(static fn (Error $error): array => [$error::class, $error->getErrorCode(), $error->getSeverity()], $result->errors)
        );
    }
}

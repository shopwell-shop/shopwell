<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\Validation;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Validation\Error\AppNameError;
use Shopwell\Core\Framework\App\Validation\Error\ConfigurationError;
use Shopwell\Core\Framework\App\Validation\Error\Error;
use Shopwell\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopwell\Core\Framework\App\Validation\Error\MissingTranslationError;
use Shopwell\Core\Framework\App\Validation\Error\NotHookableError;
use Shopwell\Core\Framework\App\Validation\ManifestValidator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class ManifestValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ManifestValidator $manifestValidator;

    protected function setUp(): void
    {
        $this->manifestValidator = static::getContainer()->get(ManifestValidator::class);
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $result = $this->manifestValidator->validate($manifest, Context::createDefaultContext());

        static::assertTrue($result->isOk(), \sprintf(
            'No error expected, got: %s',
            implode(', ', array_map(static fn ($error) => $error->getMessage(), $result->errors ?? []))
        ));
    }

    public function testValidateInvalidManifest(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/invalidManifest/manifest.xml');

        $result = $this->manifestValidator->validate($manifest, Context::createDefaultContext());

        static::assertNotNull($result->errors);
        static::assertSame(
            [MissingTranslationError::class, AppNameError::class, ConfigurationError::class, NotHookableError::class, MissingPermissionError::class],
            array_map(static fn (Error $error): string => $error::class, $result->errors)
        );
    }
}

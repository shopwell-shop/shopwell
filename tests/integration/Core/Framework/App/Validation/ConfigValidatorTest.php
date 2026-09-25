<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\Validation;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Validation\ConfigValidator;
use Shopwell\Core\Framework\App\Validation\Error\ConfigurationError;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class ConfigValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ConfigValidator $configValidator;

    protected function setUp(): void
    {
        $this->configValidator = static::getContainer()->get(ConfigValidator::class);
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/withConfig/manifest.xml');

        $violations = $this->configValidator->validate($manifest, null);
        static::assertCount(0, $violations->getElements());
    }

    public function testValidateReturnsErrors(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Lifecycle/_fixtures/withInvalidConfig/manifest.xml');

        $violations = $this->configValidator->validate($manifest, null);

        static::assertCount(1, $violations->getElements());
        static::assertInstanceOf(ConfigurationError::class, $violations->first());
        static::assertStringContainsString('The following custom components are not allowed to be used in app configuration:', $violations->first()->getMessage());
        static::assertStringContainsString('- test', $violations->first()->getMessage());
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Console\ShopwellStyle;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Feature\FeatureException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwellStyle::class)]
class ShopwellStyleTest extends TestCase
{
    use EnvTestBehaviour;

    #[TestDox('createProgressBar throws, because the class deprecation is enforced when v6.8.0.0 is active')]
    public function testCreateProgressBarThrowsWhenV68IsActive(): void
    {
        $style = new ShopwellStyle(new ArrayInput([]), new BufferedOutput());

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage(ShopwellStyle::class, 'v6.8.0.0')
        ));
        $style->createProgressBar();
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('createProgressBar applies the magenta bar character')]
    public function testCreateProgressBarAppliesBarCharacter(): void
    {
        $style = new ShopwellStyle(new ArrayInput([]), new BufferedOutput());

        $progressBar = $style->createProgressBar(10);

        static::assertSame('<fg=magenta>=</>', $progressBar->getBarCharacter());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('createProgressBar takes the progress character from the PROGRESS_BAR_CHARACTER env variable')]
    public function testCreateProgressBarUsesProgressCharacterFromEnvironment(): void
    {
        $this->setEnvVars(['PROGRESS_BAR_CHARACTER' => '#']);
        $style = new ShopwellStyle(new ArrayInput([]), new BufferedOutput());

        $progressBar = $style->createProgressBar(10);

        static::assertSame('#', $progressBar->getProgressCharacter());
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\MeasurementSystem\TwigExtension;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
class MeasurementConvertUnitTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = static::getContainer()->get('twig');
    }

    public function testConvertUnitFilterCanBeUsedInTwigTemplate(): void
    {
        $template = $this->twig->createTemplate('{{ 1000|sw_convert_unit("mm", "m") }}');

        static::assertSame('1 m', $template->render());
    }
}

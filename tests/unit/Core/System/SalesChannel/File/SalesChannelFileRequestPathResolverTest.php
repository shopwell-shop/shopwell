<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SalesChannel\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopwell\Core\System\SalesChannel\SalesChannelException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileRequestPathResolver::class)]
class SalesChannelFileRequestPathResolverTest extends TestCase
{
    public function testItBuildsTemplatePathForNestedPublicFile(): void
    {
        $templatePath = (new SalesChannelFileRequestPathResolver())->buildTemplatePath('agentic', '.well-known/ucp.json');

        static::assertSame('files/agentic/.well-known/ucp.json.twig', $templatePath);
    }

    public function testItRejectsFileFamilyLongerThanDatabaseColumn(): void
    {
        $fileFamily = str_repeat('a', 65);

        $this->expectExceptionObject(SalesChannelException::invalidSalesChannelFileFamily($fileFamily));

        (new SalesChannelFileRequestPathResolver())->validateFileFamily($fileFamily);
    }
}

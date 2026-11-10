<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Blog;

use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Service\BlogTypeRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @internal
 */
#[Package('discovery')]
class BlogTypeControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testRegisteredTypesAreAvailableThroughTheAdminApi(): void
    {
        $browser = $this->getBrowser();
        $browser->request('GET', '/api/_action/blog/types');

        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent() ?: '');
        $body = json_decode($browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(static::getContainer()->get(BlogTypeRegistry::class)->getTypes(), $body);
        static::assertSame(['post', 'video'], $body);

        $document = json_decode((string) file_get_contents(\dirname(__DIR__, 5) . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/blog-types.json'), false, 512, \JSON_THROW_ON_ERROR);
        $schema = PropertyAccess::createPropertyAccessor()->getValue($document, 'paths./_action/blog/types.get.responses.200.content.application/json.schema');
        $result = (new Validator())->validate(json_decode($browser->getResponse()->getContent() ?: '', false, 512, \JSON_THROW_ON_ERROR), $schema);
        static::assertTrue($result->isValid(), 'The served type identifiers must match its declared response schema.');
    }
}

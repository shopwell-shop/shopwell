<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\File\FileSaver;
use Shopwell\Core\Content\Media\File\MediaFile;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentValidator;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentValidatorTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');

        $this->productRepository->create([(new ProductBuilder($this->ids, 'drill'))->price(10)->build()], $this->context);
    }

    public function testTheFilesOfAllLanguagesAreChecked(): void
    {
        $chineseId = $this->getZhCnLanguageId();

        // an Admin API request runs in the user scope, where the media repository hides private files
        $adminApiSource = new AdminApiSource(null);
        $adminApiSource->setIsAdmin(true);

        $errors = $this->errorsOf(fn () => $this->productRepository->update([[
            'id' => $this->ids->get('drill'),
            'documents' => [[
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual', 'mediaId' => $this->createMedia('manual')],
                    $chineseId => ['title' => '说明书', 'mediaId' => $this->createMedia('handbuch', private: true)],
                ],
            ]],
        ]], Context::createDefaultContext($adminApiSource)));

        // the Admin API user can't see the private file, so it counts as not found
        static::assertSame([
            ['code' => ProductDocumentValidator::VIOLATION_FILE_NOT_FOUND, 'pointer' => '/0/documents/0/translations/' . $chineseId . '/mediaId'],
        ], $errors);
    }

    public function testADocumentWithoutAFileDoesNotGetTheFileOfAnotherRecordOfTheWrite(): void
    {
        $errors = $this->errorsOf(fn () => $this->productRepository->update([[
            'id' => $this->ids->get('drill'),
            'cover' => ['media' => ['id' => $this->createMedia('cover')]],
            'documents' => [['translations' => [Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual']]]],
        ]], $this->context));

        static::assertSame([
            ['code' => NotBlank::IS_BLANK_ERROR, 'pointer' => '/0/documents/0/translations/' . Defaults::LANGUAGE_SYSTEM . '/mediaId'],
        ], $errors);
    }

    public function testReplacingADocumentFileWithAnotherTypeKeepsTheOldFile(): void
    {
        $this->productRepository->update([[
            'id' => $this->ids->get('drill'),
            'documents' => [['title' => 'Manual', 'mediaId' => $this->createMedia('manual')]],
        ]], $this->context);

        try {
            static::getContainer()->get(FileSaver::class)->persistFileToMedia(
                new MediaFile('manual.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx', 1024),
                'manual',
                $this->ids->get('manual'),
                $this->context,
            );

            static::fail('The replace was not stopped.');
        } catch (ConstraintViolationException $exception) {
            static::assertSame([ProductDocumentValidator::VIOLATION_FILE_EXTENSION_NOT_ALLOWED], array_column(iterator_to_array($exception->getErrors(), false), 'code'));
        }

        $media = $this->mediaRepository->search(new Criteria([$this->ids->get('manual')]), $this->context)->getEntities()->get($this->ids->get('manual'));
        static::assertSame('pdf', $media?->getFileExtension());
    }

    private function createMedia(string $fileName, bool $private = false): string
    {
        $this->mediaRepository->create([[
            'id' => $this->ids->create($fileName),
            'fileName' => $fileName,
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
            'fileSize' => 1024,
            'private' => $private,
        ]], $this->context);

        return $this->ids->get($fileName);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function errorsOf(\Closure $write): array
    {
        try {
            $write();
        } catch (WriteException $exception) {
            $errors = [];
            foreach ($exception->getErrors() as $error) {
                $errors[] = ['code' => $error['code'], 'pointer' => $error['source']['pointer'] ?? null];
            }

            return $errors;
        }

        static::fail('The write did not fail.');
    }
}

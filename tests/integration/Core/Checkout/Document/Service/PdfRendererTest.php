<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Document\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Document\DocumentConfiguration;
use Shopwell\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopwell\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopwell\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopwell\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopwell\Core\Checkout\Document\Service\DocumentGenerator;
use Shopwell\Core\Checkout\Document\Service\PdfRenderer;
use Shopwell\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class PdfRendererTest extends TestCase
{
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private DeliveryNoteRenderer $deliveryNoteRenderer;

    private DocumentGenerator $documentGenerator;

    private PdfRenderer $pdfRenderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
        $priceRuleId = Uuid::randomHex();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
            ]
        );

        $this->salesChannelContext->setRuleIds([$priceRuleId]);
        $this->deliveryNoteRenderer = static::getContainer()->get(DeliveryNoteRenderer::class);
        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        $this->pdfRenderer = static::getContainer()->get(PdfRenderer::class);
    }

    public function testRender(): void
    {
        // generates one line item for each tax
        $cart = $this->generateDemoCart(3);

        // generates credit items for each price
        $orderId = $this->persistCart($cart);

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, FileTypes::PDF, $invoiceConfig->jsonSerialize());

        $invoice = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operationInvoice], $this->context)->getSuccess()->first();
        static::assertNotNull($invoice);
        $invoiceId = $invoice->getId();

        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            [
                'displayLineItems' => true,
                'itemsPerPage' => 10,
                'displayFooter' => true,
                'displayHeader' => true,
            ],
            $invoiceId
        );

        $processedTemplate = $this->deliveryNoteRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());

        $rendered = $processedTemplate->getSuccess()[$orderId];

        $generatorOutput = $this->pdfRenderer->render($rendered);
        static::assertNotEmpty($generatorOutput);

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        static::assertSame('application/pdf', $finfo->buffer($generatorOutput));
    }
}

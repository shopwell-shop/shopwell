<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\ContactForm;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('store-api')]
class ContactFormRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testContactFormSendMail(): void
    {
        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->browser->request(
            'POST',
            '/store-api/contact-form',
            [
                'gender' => $this->getValidGender(),
                'name' => 'Firstname Lastname',
                'email' => 'test@shäpware.com',
                'phone' => '12345/6789',
                'subject' => 'Subject',
                'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('individualSuccessMessage', $response);
        static::assertSame('', $response['individualSuccessMessage']);

        static::assertInstanceOf(MailSentEvent::class, $mail);
        $html = $mail->getContents()['text/html'];
        static::assertIsString($html);
        static::assertStringContainsString('Contact email address: test@xn--shpware-6wa.com', $html);
        static::assertStringContainsString('Lorem ipsum dolor sit amet', $html);
    }

    public function testContactFormUsesSalesChannelRecipient(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set('core.basicInformation.email', 'h.mac@example.com', $this->ids->get('sales-channel'));

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->browser->request(
            'POST',
            '/store-api/contact-form',
            [
                'gender' => $this->getValidGender(),
                'name' => 'Firstname Lastname',
                'email' => 'test@shopwell.cn',
                'phone' => '12345/6789',
                'subject' => 'Subject',
                'comment' => 'Lorem ipsum dolor sit amet',
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('individualSuccessMessage', $response);
        static::assertSame('', $response['individualSuccessMessage']);

        static::assertInstanceOf(MailSentEvent::class, $mail);
        static::assertArrayHasKey('h.mac@example.com', $mail->getRecipients());
        $html = $mail->getContents()['text/html'];
        static::assertIsString($html);
        static::assertStringContainsString('Contact email address: test@shopwell.cn', $html);
        static::assertStringContainsString('Lorem ipsum dolor sit amet', $html);
    }

    #[DataProvider('contactFormWithDomainProvider')]
    public function testContactFormWithInvalid(string $name, \Closure $expectClosure): void
    {
        $this->browser->request(
            'POST',
            '/store-api/contact-form',
            [
                'gender' => $this->getValidGender(),
                'name' => $name,
                'email' => 'test@shopwell.cn',
                'phone' => '12345/6789',
                'subject' => 'Subject',
                'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $expectClosure($response);
    }

    public static function contactFormWithDomainProvider(): \Generator
    {
        yield 'subscribe with URL protocol HTTPS' => [
            'Y https://shopwell.test',
            static function (array $response): void {
                static::assertArrayHasKey('errors', $response);
                static::assertCount(1, $response['errors']);

                $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

                static::assertContains('/name', $errors);
            },
        ];

        yield 'subscribe with URL protocol HTTP' => [
            'Y http://shopwell.test',
            static function (array $response): void {
                static::assertArrayHasKey('errors', $response);
                static::assertCount(1, $response['errors']);

                $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

                static::assertContains('/name', $errors);
            },
        ];

        yield 'subscribe with URL localhost' => [
            'Y http://localhost:8080',
            static function (array $response): void {
                static::assertArrayHasKey('errors', $response);
                static::assertCount(1, $response['errors']);

                $errors = array_column(array_column($response['errors'], 'source'), 'pointer');

                static::assertContains('/name', $errors);
            },
        ];
    }
}

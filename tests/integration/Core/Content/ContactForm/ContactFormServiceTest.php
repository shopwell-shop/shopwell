<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\ContactForm;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ContactForm\SalesChannel\ContactFormRoute;
use Shopwell\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Shopwell\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class ContactFormServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;

    private ContactFormRoute $contactFormRoute;

    protected function setUp(): void
    {
        $this->contactFormRoute = static::getContainer()->get(ContactFormRoute::class);
    }

    public function testContactFormSendMail(): void
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $validationEventDidRun = false;
        $validationListenerClosure = static function () use (&$validationEventDidRun): void {
            $validationEventDidRun = true;
        };

        $validationEventName = 'framework.validation.contact_form.create';

        $this->addEventListener(static::getContainer()->get('event_dispatcher'), $validationEventName, $validationListenerClosure);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.nameFieldRequired', true);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', true);
        $systemConfig->set('core.basicInformation.email', 'doNotReply@example.com');

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Firstname Lastname',
            'email' => 'test@shopwell.cn',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertTrue($validationEventDidRun, "The $validationEventName Event did not run");
        static::assertInstanceOf(MailSentEvent::class, $mail);
        $html = $mail->getContents()['text/html'];
        static::assertIsString($html);
        static::assertStringContainsString('Contact email address: test@shopwell.cn', $html);
        static::assertStringContainsString('Lorem ipsum dolor sit amet', $html);
    }

    public function testContactFormNameRequiredException(): void
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.nameFieldRequired', true);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'name' => ' ',
            'email' => 'test@shopwell.cn',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
    }

    public function testContactFormPhoneNumberRequiredException(): void
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.nameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', true);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Firstname Lastname',
            'email' => 'test@shopwell.cn',
            'phone' => '',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
    }

    public function testContactFormOptionalFieldsSendMail(): void
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.nameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'name' => ' ',
            'email' => 'test@shopwell.cn',
            'phone' => '',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailSentEvent::class, $mail);
        $html = $mail->getContents()['text/html'];
        static::assertIsString($html);
        static::assertStringContainsString('Contact email address: test@shopwell.cn', $html);
        static::assertStringContainsString('Lorem ipsum dolor sit amet', $html);
    }
}

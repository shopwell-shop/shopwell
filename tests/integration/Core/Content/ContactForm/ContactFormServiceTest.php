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

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $eventDidRun = false;
        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun): void {
            $eventDidRun = true;
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('Contact email address: test@shopwell.cn', $htmlText);
            static::assertStringContainsString('Lorem ipsum dolor sit amet', $htmlText);
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $validationEventDidRun = false;
        $validationListenerClosure = static function () use (&$validationEventDidRun): void {
            $validationEventDidRun = true;
        };

        $validationEventName = 'framework.validation.contact_form.create';

        $this->addEventListener($dispatcher, $validationEventName, $validationListenerClosure);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', true);
        $systemConfig->set('core.basicInformation.email', 'doNotReply@example.com');

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'email' => 'test@shopwell.cn',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);
        $dispatcher->removeListener($validationEventName, $validationListenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
        static::assertTrue($validationEventDidRun, "The $validationEventName Event did not run");
    }

    public function testContactFormFirstNameRequiredException(): void
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $listenerClosure = static function (MailSentEvent $event): void {
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('Contact email address: test@shopwell.cn', $htmlText);
            static::assertStringContainsString('Lorem ipsum dolor sit amet', $htmlText);
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => '',
            'lastName' => 'Lastname',
            'email' => 'test@shopwell.cn',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);
    }

    public function testContactFormLastNameRequiredException(): void
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $listenerClosure = static function (MailSentEvent $event): void {
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('Contact email address: test@shopwell.cn', $htmlText);
            static::assertStringContainsString('Lorem ipsum dolor sit amet', $htmlText);
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', true);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Firstname',
            'lastName' => '',
            'email' => 'test@shopwell.cn',
            'phone' => '12345/6789',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);
    }

    public function testContactFormPhoneNumberRequiredException(): void
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $listenerClosure = static function (MailSentEvent $event): void {
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('Contact email address: test@shopwell.cn', $htmlText);
            static::assertStringContainsString('Lorem ipsum dolor sit amet', $htmlText);
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', true);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'email' => 'test@shopwell.cn',
            'phone' => '',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->expectException(ConstraintViolationException::class);
        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);
    }

    public function testContactFormOptionalFieldsSendMail(): void
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $eventDidRun = false;
        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun): void {
            $eventDidRun = true;
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('Contact email address: test@shopwell.cn', $htmlText);
            static::assertStringContainsString('Lorem ipsum dolor sit amet', $htmlText);
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.firstNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.lastNameFieldRequired', false);
        $systemConfig->set('core.basicInformation.phoneNumberFieldRequired', false);

        $dataBag = new DataBag();
        $dataBag->add([
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => '',
            'lastName' => '',
            'email' => 'test@shopwell.cn',
            'phone' => '',
            'subject' => 'Subject',
            'comment' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
        ]);

        $this->contactFormRoute->load($dataBag->toRequestDataBag(), $context);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\Audit\SmsTemplateBodyReader;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateRenderer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsTemplateRenderer::class)]
class SmsTemplateRendererTest extends TestCase
{
    public function testItReplacesThePlaceholdersOfTheContent(): void
    {
        $template = $this->createTemplate('Your code is {{ code }}, it is valid for {{minutes}} minutes.');

        static::assertSame(
            'Your code is 123456, it is valid for 5 minutes.',
            $this->createRenderer()->render($template, ['code' => '123456', 'minutes' => 5])
        );
    }

    public function testItLeavesABodyWithoutAPlaceholderAsItIs(): void
    {
        $template = $this->createTemplate('Your order was shipped.');

        static::assertSame('Your order was shipped.', $this->createRenderer()->render($template, []));
    }

    public function testItThrowsWhenTheContentIsMissing(): void
    {
        $template = $this->createTemplate(null);

        $this->expectExceptionObject(SmsException::templateContentMissing('customer.otp.login'));

        $this->createRenderer()->render($template, []);
    }

    public function testItThrowsWhenTheContentIsEmpty(): void
    {
        $template = $this->createTemplate('   ');

        $this->expectExceptionObject(SmsException::templateContentMissing('customer.otp.login'));

        $this->createRenderer()->render($template, []);
    }

    public function testItFallsBackToTheBodyOfTheDefaultLanguage(): void
    {
        // a language that has no body of its own leaves the property null, the body of the default
        // language stays reachable as the translated value
        $template = $this->createTemplate(null);
        $template->addTranslated('content', 'Ihr Code ist {{ code }}.');

        static::assertSame(
            'Ihr Code ist 123456.',
            $this->createRenderer()->render($template, ['code' => '123456'])
        );
    }

    public function testItFallsBackWhenTheBodyOfTheLanguageIsBlank(): void
    {
        $template = $this->createTemplate('   ');
        $template->addTranslated('content', 'Your code is {{ code }}.');

        static::assertSame(
            'Your code is 123456.',
            $this->createRenderer()->render($template, ['code' => '123456'])
        );
    }

    public function testTheBodyOfTheDefaultLanguageWinsOverTheOneOfTheContext(): void
    {
        // this round reports and sends the body of the default language only, so a body of the
        // context language must never be rendered - it would differ from the reported one
        $template = $this->createTemplate('Votre code est {{ code }}.');
        $template->addTranslated('content', 'Your code is {{ code }}.');

        static::assertSame(
            'Your code is 123456.',
            $this->createRenderer()->render($template, ['code' => '123456'])
        );
    }

    public function testItRendersTheDefaultLanguageWhenTheTranslationsAreLoadedAndTheContextIsAnotherLanguage(): void
    {
        // the context language is en-US: the property carries the en-US body and both translation
        // rows are loaded, the rendered body is still the one of the default language
        $template = $this->createTemplate('Your code is {{ code }}.');
        $template->translations = [
            Defaults::LANGUAGE_SYSTEM => (new ArrayEntity())->assign(['content' => 'Ihr Code ist {{ code }}.']),
            Uuid::randomHex() => (new ArrayEntity())->assign(['content' => 'Your code is {{ code }}.']),
        ];

        static::assertSame(
            'Ihr Code ist 123456.',
            $this->createRenderer()->render($template, ['code' => '123456'])
        );
    }

    public function testItStaysMissingWhenNeitherLanguageHasABody(): void
    {
        $template = $this->createTemplate(null);
        $template->addTranslated('content', '   ');

        $this->expectExceptionObject(SmsException::templateContentMissing('customer.otp.login'));

        $this->createRenderer()->render($template, []);
    }

    public function testItThrowsWhenTheContentRefersToAParameterThatWasNotPassed(): void
    {
        $template = $this->createTemplate('Your code is {{ code }}.');

        $this->expectExceptionObject(SmsException::invalidTemplateParams(
            'customer.otp.login',
            ['the content refers to "code" which was not passed']
        ));

        $this->createRenderer()->render($template, []);
    }

    public function testItThrowsWhenTheRenderedBodyIsLongerThanTheTemplateAllows(): void
    {
        $template = $this->createTemplate('Your code is {{ code }}.', maxLength: 10);

        $this->expectExceptionObject(SmsException::messageTooLong('customer.otp.login', 20, 10));

        $this->createRenderer()->render($template, ['code' => '123456']);
    }

    public function testItAcceptsABodyAtTheUpperBound(): void
    {
        $template = $this->createTemplate('Your code is {{ code }}.', maxLength: 20);

        static::assertSame(
            'Your code is 123456.',
            $this->createRenderer()->render($template, ['code' => '123456'])
        );
    }

    private function createRenderer(): SmsTemplateRenderer
    {
        return new SmsTemplateRenderer(new SmsTemplateBodyReader());
    }

    private function createTemplate(?string $content, ?int $maxLength = null): SmsTemplateEntity
    {
        $template = new SmsTemplateEntity();
        $template->id = Uuid::randomHex();
        $template->setUniqueIdentifier($template->id);
        $template->technicalName = 'customer.otp.login';
        $template->scene = SmsTemplateEntity::SCENE_OTP;
        $template->content = $content;
        $template->maxLength = $maxLength;
        $template->enabled = true;

        return $template;
    }
}

<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Template\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Template\Audit\SmsTemplateBodyReader;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsTemplateBodyReader::class)]
class SmsTemplateBodyReaderTest extends TestCase
{
    public function testItReadsTheBodyOfTheDefaultLanguageRow(): void
    {
        $template = $this->createTemplate('Your code is {{ code }}.');
        $template->translations = [
            Defaults::LANGUAGE_SYSTEM => (new ArrayEntity())->assign(['content' => 'Ihr Code ist {{ code }}.']),
            Uuid::randomHex() => (new ArrayEntity())->assign(['content' => 'Your code is {{ code }}.']),
        ];

        static::assertSame('Ihr Code ist {{ code }}.', (new SmsTemplateBodyReader())->readDefaultContent($template));
    }

    public function testItReadsTheTranslatedValueWhenTheTranslationsAreNotLoaded(): void
    {
        // the data abstraction layer keeps the body of the default language next to the body of the
        // context language, so a lookup without the association still finds the reported body
        $template = $this->createTemplate('Your code is {{ code }}.');
        $template->addTranslated('content', 'Ihr Code ist {{ code }}.');

        static::assertSame('Ihr Code ist {{ code }}.', (new SmsTemplateBodyReader())->readDefaultContent($template));
    }

    public function testItFallsBackWhenTheRowOfTheDefaultLanguageHasNoBody(): void
    {
        $template = $this->createTemplate('Your code is {{ code }}.');
        $template->addTranslated('content', 'Ihr Code ist {{ code }}.');
        $template->translations = [Defaults::LANGUAGE_SYSTEM => new ArrayEntity()];

        static::assertSame('Ihr Code ist {{ code }}.', (new SmsTemplateBodyReader())->readDefaultContent($template));
    }

    public function testItReturnsAnEmptyBodyWhenTheTemplateHasNone(): void
    {
        static::assertSame('', (new SmsTemplateBodyReader())->readDefaultContent($this->createTemplate(null)));
    }

    private function createTemplate(?string $content): SmsTemplateEntity
    {
        $template = new SmsTemplateEntity();
        $template->id = Uuid::randomHex();
        $template->setUniqueIdentifier($template->id);
        $template->technicalName = 'customer.otp.login';
        $template->scene = SmsTemplateEntity::SCENE_OTP;
        $template->content = $content;

        return $template;
    }
}

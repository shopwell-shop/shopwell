<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Flow\Action\Xml\InputField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InputField::class)]
class InputFieldTest extends TestCase
{
    public function testFromXml(): void
    {
        $inputField = InputField::fromXml(self::loadElement(<<<'XML'
<input-field>
    <name>message</name>
    <label>Message</label>
    <label lang="zh-CN">消息</label>
    <place-holder>Enter message...</place-holder>
    <place-holder lang="zh-CN">请输入消息...</place-holder>
    <helpText>Visible to customers</helpText>
    <helpText lang="zh-CN">客户可见</helpText>
    <required>true</required>
    <defaultValue>Hello</defaultValue>
</input-field>
XML));

        static::assertSame('message', $inputField->getName());
        static::assertSame(
            [
                'en-GB' => 'Message',
                'zh-CN' => '消息',
            ],
            $inputField->getLabel()
        );
        static::assertSame(
            [
                'en-GB' => 'Enter message...',
                'zh-CN' => '请输入消息...',
            ],
            $inputField->getPlaceHolder()
        );
        static::assertSame(
            [
                'en-GB' => 'Visible to customers',
                'zh-CN' => '客户可见',
            ],
            $inputField->getHelpText()
        );
        static::assertTrue($inputField->getRequired());
        static::assertSame('Hello', $inputField->getDefaultValue());
        static::assertSame('text', $inputField->getType());
        static::assertSame([], $inputField->getOptions());
    }

    public function testFromXmlWithOptions(): void
    {
        $inputField = InputField::fromXml(self::loadElement(<<<'XML'
<input-field type="single-select">
    <name>mailMethod</name>
    <options>
        <option value="smtp">
            <label>SMTP</label>
            <label lang="zh-CN">SMTP ZH</label>
        </option>
        <option value="pop3">
            <label>POP3</label>
        </option>
    </options>
</input-field>
XML));

        static::assertSame('mailMethod', $inputField->getName());
        static::assertSame('single-select', $inputField->getType());
        static::assertSame(
            [
                [
                    'value' => 'smtp',
                    'label' => [
                        'en-GB' => 'SMTP',
                        'zh-CN' => 'SMTP ZH',
                    ],
                ],
                [
                    'value' => 'pop3',
                    'label' => [
                        'en-GB' => 'POP3',
                    ],
                ],
            ],
            $inputField->getOptions()
        );
    }

    public function testToArray(): void
    {
        $inputField = InputField::fromXml(self::loadElement(<<<'XML'
<input-field type="text">
    <name>message</name>
    <label>Message</label>
    <required>false</required>
    <defaultValue>Hello</defaultValue>
</input-field>
XML));

        static::assertSame(
            [
                'name' => 'message',
                'label' => ['en-GB' => 'Message'],
                'placeHolder' => null,
                'required' => false,
                'helpText' => null,
                'defaultValue' => 'Hello',
                'options' => [],
                'type' => 'text',
            ],
            $inputField->toArray('en-GB')
        );
    }

    public function testToArrayAddsTranslationsForTheDefaultLocale(): void
    {
        $inputField = InputField::fromXml(self::loadElement(<<<'XML'
<input-field type="single-select">
    <name>mailMethod</name>
    <label>Mail method</label>
    <label lang="de-DE">Versandart</label>
    <place-holder>Choose a method</place-holder>
    <helpText lang="de-DE">Gilt fuer alle Mails</helpText>
    <options>
        <option value="smtp">
            <label>SMTP</label>
            <label lang="de-DE">SMTP DE</label>
        </option>
    </options>
</input-field>
XML));

        $result = $inputField->toArray('de-AT');

        static::assertSame(['en-GB' => 'Mail method', 'de-DE' => 'Versandart', 'de-AT' => 'Versandart'], $result['label']);
        static::assertSame(['en-GB' => 'Choose a method', 'de-AT' => 'Choose a method'], $result['placeHolder']);
        static::assertSame(['de-DE' => 'Gilt fuer alle Mails', 'de-AT' => 'Gilt fuer alle Mails'], $result['helpText']);
        static::assertSame(
            [
                [
                    'value' => 'smtp',
                    'label' => ['en-GB' => 'SMTP', 'de-DE' => 'SMTP DE', 'de-AT' => 'SMTP DE'],
                ],
            ],
            $result['options']
        );
    }

    /**
     * @param non-empty-string $xml
     */
    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}

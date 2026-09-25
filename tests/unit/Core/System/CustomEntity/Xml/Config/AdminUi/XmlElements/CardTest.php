<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Card;
use Shopwell\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\CardField;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Card::class)]
class CardTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $cardElement = $dom->createElement('card');
        $cardElement->setAttribute('name', 'CardTest');

        $cardFieldElement = $dom->createElement('field');

        $cardElement->appendChild(
            $cardFieldElement
        );

        $card = Card::fromXml($cardElement);

        $cardFields = $card->getFields();
        static::assertInstanceOf(CardField::class, \array_pop($cardFields));
    }
}

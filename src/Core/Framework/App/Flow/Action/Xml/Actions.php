<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Flow\Action\Xml;

use Shopwell\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Actions extends XmlElement
{
    /**
     * @var list<Action>
     */
    protected array $actions;

    /**
     * @return list<Action>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    protected static function parse(\DOMElement $element): array
    {
        $actions = [];
        foreach ($element->getElementsByTagName('flow-action') as $flowAction) {
            $actions[] = Action::fromXml($flowAction);
        }

        return ['actions' => $actions];
    }
}

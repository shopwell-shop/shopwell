<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Robots;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopwell\Storefront\Page\Robots\Parser\ParseIssueSeverity;
use Shopwell\Storefront\Page\Robots\Parser\RobotsDirectiveParser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class RobotsConfigChangeSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RobotsDirectiveParser $parser,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    public function onSystemConfigChanged(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== 'core.basicInformation.robotsRules') {
            return;
        }

        $value = $event->getValue();
        if (!\is_string($value) || $value === '') {
            return;
        }

        $salesChannelId = $event->getSalesChannelId();
        $parsed = $this->parser->parse($value, Context::createDefaultContext(), $salesChannelId);

        if ($parsed->issues === []) {
            return;
        }

        $scope = $salesChannelId === null ? 'Global' : $salesChannelId;

        foreach ($parsed->issues as $issue) {
            $message = \sprintf(
                'Robots.txt parsing issue at line %d: %s',
                $issue->lineNumber,
                $issue->reason
            );

            $context = [
                'scope' => $scope,
                'lineNumber' => $issue->lineNumber,
                'lineContent' => $issue->lineContent,
                'severity' => $issue->severity->value,
            ];

            if ($issue->severity === ParseIssueSeverity::ERROR) {
                $this->logger->error($message, $context);
            } else {
                $this->logger->warning($message, $context);
            }
        }
    }
}

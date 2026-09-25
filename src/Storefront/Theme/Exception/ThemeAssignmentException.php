<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Exception will be removed
 */
#[Package('discovery')]
class ThemeAssignmentException extends ShopwellHttpException
{
    /**
     * @param array<string, array<int, string>> $themeSalesChannel
     * @param array<string, array<int, string>> $childThemeSalesChannel
     * @param array<string, string> $assignedSalesChannels
     */
    public function __construct(
        string $themeName,
        array $themeSalesChannel,
        array $childThemeSalesChannel,
        private readonly array $assignedSalesChannels,
        ?\Throwable $e = null
    ) {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', ThemeException::class));

        $parameters = ['themeName' => $themeName];
        $message = 'Unable to deactivate or uninstall theme "{{ themeName }}".';
        $message .= ' Remove the following assignments between theme and sales channel assignments: {{ assignments }}.';
        $assignments = '';
        if ($themeSalesChannel !== []) {
            $assignments .= $this->formatAssignments($themeSalesChannel);
        }

        if ($childThemeSalesChannel !== []) {
            $assignments .= $this->formatAssignments($childThemeSalesChannel);
        }
        $parameters['assignments'] = $assignments;

        parent::__construct($message, $parameters, $e);
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', ThemeException::class));

        return 'THEME__THEME_ASSIGNMENT';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', ThemeException::class));

        return Response::HTTP_BAD_REQUEST;
    }

    /**
     * @return array<string, string>|null
     */
    public function getAssignedSalesChannels(): ?array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', ThemeException::class));

        return $this->assignedSalesChannels;
    }

    /**
     * @param array<string, array<int, string>> $assignmentMapping
     */
    private function formatAssignments(array $assignmentMapping): string
    {
        $output = [];
        foreach ($assignmentMapping as $themeName => $salesChannelIds) {
            $salesChannelNames = [];
            foreach ($salesChannelIds as $salesChannelId) {
                if ($this->assignedSalesChannels[$salesChannelId]) {
                    $salesChannel = $this->assignedSalesChannels[$salesChannelId];
                } else {
                    $salesChannelNames[] = $salesChannelId;

                    continue;
                }

                $salesChannelNames[] = $salesChannel;
            }

            $output[] = \sprintf('"%s" => "%s"', $themeName, implode(', ', $salesChannelNames));
        }

        return implode(', ', $output);
    }
}

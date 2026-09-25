<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation;

use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopwell\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopwell\Core\Framework\App\Validation\Error\NotHookableError;
use Shopwell\Core\Framework\App\Validation\Error\WebhookNotPermittedError;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopwell\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventCollector;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class HookableValidator extends AbstractManifestValidator
{
    public function __construct(
        private readonly HookableEventCollector $hookableEventCollector,
        private readonly PolicyRegistry $policies,
    ) {
    }

    public function validate(Manifest $manifest, Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();
        $webhooks = $manifest->getWebhooks();
        $webhooks = $webhooks ? $webhooks->getWebhooks() : [];

        if (!$webhooks) {
            return $errors;
        }

        $appPrivileges = $manifest->getPermissions();
        $appPrivileges = $appPrivileges ? $appPrivileges->asParsedPrivileges() : [];
        $hookableEventNamesWithPrivileges = $this->hookableEventCollector->getHookableEventNamesWithPrivileges($context, $manifest);
        $hookableEventNames = array_keys($hookableEventNamesWithPrivileges);

        $notHookable = [];
        $notPermitted = [];
        $missingPermissions = [];
        foreach ($webhooks as $webhook) {
            // validate supported webhooks
            if (!\in_array($webhook->getEvent(), $hookableEventNames, true)) {
                $notHookable[] = $webhook->getName() . ': ' . $webhook->getEvent();

                continue;
            }

            if (!$this->policies->permitsSubscription($webhook->getEvent(), Subscriber::app($manifest))) {
                $notPermitted[] = $webhook->getName() . ': ' . $webhook->getEvent();

                continue;
            }

            // validate permissions
            foreach ($hookableEventNamesWithPrivileges[$webhook->getEvent()]['privileges'] as $privilege) {
                if (\in_array($privilege, $appPrivileges, true)) {
                    continue;
                }

                $missingPermissions[] = $privilege;
            }
        }

        if ($notHookable !== []) {
            $errors->add(new NotHookableError($notHookable));
        }

        if ($notPermitted !== []) {
            $errors->add(new WebhookNotPermittedError($notPermitted));
        }

        if ($missingPermissions !== []) {
            $errors->add(new MissingPermissionError($missingPermissions));
        }

        return $errors;
    }
}

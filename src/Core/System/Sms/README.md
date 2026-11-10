# SMS

The SMS module separates provider code from configured accounts and message routing.
It uses DAL entities for configuration, Symfony Notifier for transport events and Messenger for asynchronous delivery.

## Responsibilities

| Directory | Responsibility |
| --- | --- |
| `Provider` | Provider registration, configuration declarations and optional template approval capabilities |
| `Integration/<Vendor>` | Vendor clients, transports and template approval adapters |
| `Gateway` | Configured provider accounts and write validation |
| `Routing` | Account eligibility, priority and effective template mapping selection |
| `Template` | Logical templates, parameter validation, rendering and approval workflows |
| `Transport` | Lazy account transport loading and shared Notifier transport behavior |
| `Service` | Business entry point through `AbstractSmsSender` |
| `Message` | Messenger delivery handler |
| `Event`, `Subscriber` | Delivery lifecycle and recording |
| `Api` | Administration actions |

Providers are stateless services.
An `sms_gateway` is an account of one provider, with its credentials, connection options, sales channel and routing priority.
A plugin may supply multiple accounts through the same provider.

## Register a provider

Extend `Provider\AbstractSmsProvider` and register the service with `shopwell.sms.provider`.
Technical names must be nonempty and unique across all providers.
The development baseline uses this registration contract exclusively.

```xml
<service id="Acme\Sms\AcmeSmsProvider">
    <argument type="service" id="http_client"/>
    <tag name="shopwell.sms.provider"/>
</service>
```

A provider declares credentials and connection fields using `SmsConfigField`.
Administration renders these declarations without a vendor-specific component.
The same declarations validate enabled accounts at the DAL write boundary.
Disabled accounts can be saved while configuration is incomplete.

```php
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Provider\Config\SmsConfigField;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AcmeSmsProvider extends AbstractSmsProvider
{
    public function __construct(private readonly HttpClientInterface $client)
    {
    }

    public function getTechnicalName(): string
    {
        return 'acme';
    }

    public function getLabelSnippet(): string
    {
        return 'acme.sms.label';
    }

    public function getCredentials(): array
    {
        return [new SmsConfigField('token', 'acme.sms.token', type: 'secret', required: true)];
    }

    public function getOptions(): array
    {
        return [new SmsConfigField('sender', 'acme.sms.sender', required: true)];
    }

    public function createTransport(
        array $credentials,
        array $options,
        ?EventDispatcherInterface $dispatcher = null,
    ): TransportInterface {
        $this->requireCredentials($credentials);

        return new AcmeSmsTransport($this->client, $credentials['token'], $options['sender'], $dispatcher);
    }
}
```

`AcmeSmsTransport` is the plugin's Notifier transport implementation.
Use the built-in integrations as examples of translating provider responses into `SentMessage` and `SmsException`.
Use an unrecoverable SMS exception for credentials or requests that cannot succeed on retry.
Provider acceptance is recorded as `sent`; it does not represent a delivery receipt.

Fields support `string`, `secret`, `json`, `bool`, `int` and `select`.
Optional metadata includes `default`, `helpSnippet` and `options` with `value` and `labelSnippet` entries.
Defaults prefill the Administration form; API clients must persist required values explicitly.
Plugins provide their own Administration snippets for these keys.
Unrecognized connection options survive edits to declared fields.

## Template capabilities

`supportsTemplateMode()` controls whether the transport uses a provider template code.
Approval is independent of template transport support.
Implement only the capabilities the provider supports from `Provider\Audit`:

- `RequiresTemplateApprovalInterface` blocks delivery until the effective mapping is approved.
- `SubmitsTemplatesForApprovalInterface` declares the submission form and submits templates.
- `ReadsTemplateApprovalStatusInterface` reads the provider's current approval state.

The Administration discovers submission and refresh actions from these capabilities.
It supports manual confirmation for providers without a status API.
A template mapping for a specific account takes precedence over a provider-wide mapping, including rejected or pending account mappings.
Routing and message construction consume the same selected mapping.

## Routing and delivery

Routing filters enabled accounts by sales channel, supported country and template approval, then orders them by priority.
Providers serve all countries by default and may narrow `getSupportedRegions()` to ISO country codes.
Unknown countries require global support or the account's explicit routing setting.
Subscribe to `SelectSmsGatewayExtension` through the framework extension dispatcher to reorder or remove eligible accounts.
The extension cannot add accounts that failed eligibility checks.

Call `AbstractSmsSender` from business code.
OTP delivery is synchronous; transaction and marketing messages use the existing Messenger routes.
Routing and transport caches reset between worker messages so configuration changes are applied to later messages.
No automatic provider failover is performed.

## Administration API

`GET /api/_action/sms/gateway-providers` exposes provider metadata, credential definitions, option definitions and approval capabilities.
The `sw-settings-sms` module renders that metadata through the shared SMS settings page shell.
Route privileges and action privileges remain separate for accounts, templates and delivery records.

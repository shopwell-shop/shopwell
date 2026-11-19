# 6.7.0.0 (upcoming)

## Core

### Approval Flow skeleton

The independent Approval Flow module provides exact-version node declarations through `NodeTypeLoader` and the `approval_flow.node_type_loader` service tag.
Business modules register their loaders in their own DI; a declaration alone does not make a node executable.
Business modules can also register `NodeCompiler` services with `approval_flow.node_compiler` to compile exact node versions into typed `NodeConfiguration` values.
Configuration compilation rejects missing or conflicting compilers and invalid semantics.
Register exact-version `NodeExecutor` entry services with `approval_flow.node_executor`; node-plan compilation now rejects missing executors without calling them.
Executors return controlled advance, approval-request or completion intents; `ApprovalOutcome` represents approved/rejected outcomes as an enum.
These services do not publish releases, establish executable artifact pins or authorize task creation and process execution.
Declarations may supply an explicit `draftConfigurationSchema`; otherwise drafts retain the complete schema's rules.
Use `approval-flow:graph:diagnose <file>` to inspect draft structure, incomplete configuration and unavailable capabilities without saving or executing the graph.
Approval definitions and drafts now have DAL models; writes require the controlled draft path so graph admission, expected revisions and explicit removal confirmation cannot be skipped through ordinary repository or Sync calls.
An unavailable exact node declaration keeps an existing draft readable and blocks edits until the declaration returns.
The legacy Workflow navigation is hidden during the replacement, while its existing routes and business execution remain available.
The new Approval navigation and business pages will be added with their corresponding capabilities.

### Dynamic workflow reviewers

Approval sources now include `core:initiator`, `core:roles` (`roleIds`) and `core:supervisors`.
Supervisor and department-leader sources accept `level` (1–20) and `continuous` (boolean) and resolve the reporting relationship from the initiator’s captured department.
A missing target level does not shorten the approval quorum.
Approval configuration accepts `missingAssignee` with an explicit `action`: `block` (default), `approve`, `reject` or `assign` with `userId`.
Publication validates replacement reviewers; preview and node entry apply the same policy and retain automatic decisions in node execution output.
Malformed reporting snapshots, unsupported sources and operational errors do not trigger automatic decisions.
Workflow viewers gain `acl_role:read` for role selection; selected role membership remains live until node entry.


### People and department workflow fields

Workflow request schemas now support `user`, `users` and `department` fields with an optional frozen `candidateIds` range.
Submit a UUID for single selections or an ordered UUID list for `users`; unavailable or out-of-range selections are rejected by the server.
Approval nodes can use `core:form-field` with `config.fieldKey` referencing a people field, so requesters choose reviewers within the published range.
Existing workflow roles receive read access to organizations and departments for these selectors.


### Automatic workflow approvals

Approval nodes accept an opt-in `automation` policy with `decision` (`manual`, `approve` or `reject`), `approveInitiator` and `deduplication` (`none`, `adjacent` or `all`).
Explicit automatic decisions retain their reason in node execution output without creating human tasks.
Participant automation records completed approval tasks and reasons while preserving the selected approval mode's quorum.
Deduplication uses successful decisions from the current submission round; missing required review input keeps a participant task manual.
Automatic completions emit completion events without creating pending-task notifications.

### Approval form permissions

Approval and copy nodes support frozen `formPermissions` keyed by the published form field key.
Approval fields default to read-only; editable fields may be required at a node, while copy nodes support only hidden and read-only access.
`ApprovalFormPolicyEvent` lets business integrations further restrict field visibility and editing without changing the canvas or workflow engine.
Transfer evidence stays immutable; optional supplemental workflow fields can be edited independently of payout inputs.

The approve and resubmit actions accept a `formData` patch with the `formVersion` from the authorized instance response.
Only changed editable keys may be submitted; omitted values are retained, and stale edits return `FRAMEWORK__WORKFLOW_FORM_CONFLICT`.
Form updates, private change history and approval progression share one transaction.
Instance details expose projected `formPermissions`, `formVersion` and `formChanges`; select a task or copy using `taskId` when `formContextRequired` is true.
Raw instance form values, graph snapshots, extension metadata, log payloads and node outputs are no longer exposed through generic DAL serialization.
Read runtime details through the authorized workflow instance endpoint.
`WorkflowInstancePresentationEvent::filterData()` applies response projections after subscribers add their data.

### User organization assignments

The installation migration seeds the editable Shopwell organization, Headquarters department and Employee position.
New users receive their initial department and position from these organization records.
Write department assignments through `user.organizationUnitMappings` and scoped positions through `user.positionMappings` or their Admin API repositories; `user.positions` is a read-only projection.
Each normal user requires exactly one primary department and primary position; use the Sync API when replacing related assignments together.
Department memberships expose `isLeader`, `leaderPosition` and `supervisorMembershipId` to the Admin API.
A supervisor references a specific membership in the same organization, so reporting lines remain explicit for users with multiple departments.
Approval workflows can select `core:department-leaders` as their assignee source with an empty configuration.
This source uses the initiator's primary department captured at instance start and supports all-must-approve or first-decision modes.
Department and position sources resolve current eligible staff within that captured department when entering each node; later changes to the initiator's department do not redirect existing instances.
The user editor configures department leaders and department-specific positions.
Assignees are resolved when entering a node, must be active and authorized, and remain attached to tasks already created when organization relationships change.
Core now directly requires `symfony/expression-language` with a minimum version of 7.4.21 within the 7.4 series.

### Experimental Workflow Studio

Approval nodes accept an optional `deadline` with `minutes` (1–525600 elapsed minutes) and `remind` (boolean); publication freezes it in the runtime snapshot.
Transfers retain the deadline, while new sequential and added reviewers receive their own time limit.
Initiators may send private reminders through `POST /api/_action/workflow/instance/{instanceId}/remind`, subject to a 600-second task cooldown.
Task projections expose a `reminder` receipt with deadline, overdue status and latest reminder; reminder actions are separate from approval decisions.
The `workflow.approval.reminder` scheduled task runs every minute, retries pending delivery and records one automatic overdue reminder per task.
Approval reminder actions and their scheduled handler require an independent transaction; plugins must invoke them after their business transaction commits.
Calls inside an existing transaction fail with `FRAMEWORK__WORKFLOW_REMINDER_TRANSACTION_ACTIVE` to prevent stale REPEATABLE READ snapshots from bypassing admission.
Run the regular scheduled task registration and message queue workers to enable automatic processing.
Plugins may narrow admission through `ApprovalReminderPolicyEvent` and deliver additional channels through `ApprovalReminderDeliveryEvent` after commit.
Delivery is at least once: use the stable `deliveryId` to deduplicate external effects, and throw on failure to retry without adding another receipt or audit entry.

Approval task owners may use `POST /api/_action/workflow/task/{taskId}/return` with a reason to request changes without ending the instance.
The initiator may resubmit through `POST /api/_action/workflow/instance/{instanceId}/resubmit` with the expected `round`, replacement `formData` and an optional `comment`.
Resubmission uses the saved graph and input schema, creates a new round and keeps prior decisions and copy receipts as history.
`ApprovalResubmittingEvent` allows business integrations to validate replacement inputs before writes; bound transfer inputs remain immutable.
The public instance status includes `RETURNED`, and workflow runtime consumers receive `returned` while the initiator prepares changes.

Workflow Studio provides editable workflow drafts, an approval template, automatic diagnostics and JSON export.
Its shared canvas editor is available independently through `CanvasEditorSession<TDocument>`, `sw-canvas-editor` and `sw-canvas-contributions`.
The shared layer provides graph history, commands and optional shell regions; the canvas accepts native Vue Flow nodes, edges, renderer slots, component maps and event listeners.
Workflow supplies its node types, layouts, forms and publication behavior.
Canvas extensions cannot commit malformed graph structures, and individual contribution disposers remove their owned dialog state without affecting later registrations.
Workflow Studio extensions register through `workflowExtensionService.register()` and receive an isolated context for commands, session events, UI contributions and resource cleanup in each editor session.
Document edits share access checks, undo/redo and the existing diagnostics pipeline; retained extension contexts become inactive on teardown.
The designer opens node selection beside hovered or focused connections, stages node configuration in a right-side panel and previews templates before explicit replacement.
Unapplied settings are protected during selection changes, extension commands and page navigation; applying them creates one undoable edit.
Opening panels preserves the canvas zoom and only pans when needed to keep the selected node visible.
See `technical-docs/03-extensibility/09-workflow-studio.md` in the Administration for the extension contract and registration example.
Registered layout callbacks receive an optional third argument containing measured node sizes and resolved output port order.
The built-in layouts reserve routing space for branch labels and insertion controls, including nodes supplied by extensions.
The read-only instance diagram reuses this geometry and shows participant states inside approval nodes, with zoom controls and an expanded view.
Transfer initiators can use `POST /api/_action/payment/transfers/{id}/reapply` after rejection or withdrawal, supplying a fresh `outNo` for retry safety.
The new request uses the active workflow release and exposes its original request through `previousTransfer` in the approval status response.
Business modules can register context-specific text and choice fields through `workflowFieldService.register(name, provider)` for condition configuration.
Providers return field metadata for the current document and node; the default approval template declares request inputs consumed by the core approval runtime.
Condition nodes support ordered text conditions, dynamic output ports and a fallback path through the node extension services.
The Admin API exposes `GET /api/_info/workflow-node-types.json` and `POST /api/_action/workflow/diagnose`, both requiring `workflow:read`.
The experimental definition and presentation registries support custom node types independently of the canvas.
The built-in node catalog contains only implemented types; action and handler nodes require an extension providing their definition, presentation, publication and execution.
`WorkflowInstanceMetadataEvent` collects extension-owned JSON metadata under distinct owner keys before the first node runs, storing it in `workflow_instance.ext`.
The same event runs for previews without persistence, so subscribers must only read and must not trigger external side effects.
Drafts can be created, reopened, renamed and deleted under Workflow > Process design.
The revision-checked draft API at `/api/_action/workflow/draft` requires the corresponding `workflow:create`, `workflow:read`, `workflow:update` or `workflow:delete` privilege; listing uses `workflow_draft:read`.
Updates and deletes require `expectedRevision` and return HTTP 409 on conflicting edits.
Approval nodes support a single named user or multiple users with all-must-approve and first-decision rules, and display current approver names.
`workflowApprovalService.registerSource()` and `registerMode()` provide independent settings components for business extensions; sources also provide their node summary component and optional `supportedModes` to constrain compatible rules.
When a source declares `supportedModes`, extensions opt in custom rules through `workflowApprovalService.registerSupportedMode(sourceName, modeName)`.
Approval mode registrations accept an explicit positive `version`; saved nodes select that exact implementation, and unavailable versions are not replaced automatically.
Source compatibility entries use a mode name for version 1 or `name@version` for later versions; `registerSupportedMode(sourceName, modeName, version)` owns a version-specific opt-in.
Backend strategies implement `ApprovalMode` and register the `shopwell.workflow.approval.mode` tag; participant configuration compilers implement `ApprovalAssigneeSource` with the `shopwell.workflow.approval.assignee_source` tag.
`CompileApprovalConfigurationExtension` supports publication-time selection overrides; the resulting strategy type, version and configuration are pinned in the execution graph and instance snapshot.
Keep referenced versions installed while published definitions or running instances use them.
Approval publication node compilers implement `ApprovalNodeCompiler` and register `shopwell.workflow.approval.node_compiler` for an exact designer type and version.
All built-in approval node types use this contract; compilers validate configuration and return runtime properties, input ports, ordered routed outputs and executor-completed terminal ports.
`CompileApprovalNodeExtension` selects registered replacements of the same structural role; duplicate type/version registrations fail.
Result substitution and short-circuiting are rejected so the recorded compiler always produced the published node.
The actual compiler identity and version, runtime configuration and source-port mapping are saved in the published graph.
Published definitions execute their snapshot without recompilation; keep their runtime executors and referenced approval strategies installed, and use a distinct runtime type when execution semantics change.
Custom nodes must also register their designer presentation and runtime executor; business preview support is a separate extension.
`ApprovalNodePreviewEvent` allows subscribers to contribute or replace a node preview from the published graph and normalized execution context.
`ApprovalWorkflowValidationEvent` lets node extensions validate live business prerequisites during publication and release revalidation, rejecting invalid prerequisites with a domain exception.
Preview subscribers must remain read-only; unhandled nodes make the preview unavailable rather than disappearing from the path.
The approval designer now exposes any approval (`core:or-sign`) and sequential approval (`core:sequential`) separately from first decision (`core:any`), whose execution semantics remain unchanged.
The designer and task views distinguish first-decision approval from any-one-approves: first decision ends on either approval or rejection, while any-one-approves waits after a rejection unless everyone rejects.
Copy nodes (`core:copy`, version 1) publish nonblocking recipient receipts and require active recipients with `workflow_instance:access`, without requiring approval decision privileges.
Participants can list their receipts through `GET /api/_action/workflow/copy` and acknowledge their own receipt through `POST /api/_action/workflow/copy/{copyId}/read`; repeated acknowledgements preserve the first read timestamp.
The structured approval publisher still requires an approval stage and supports sequential routing rather than parallel joins or subprocess scheduling.
Default node names, request labels, fallback labels and newly installed workflow role text use the system translator with English and Chinese resources.
Authored names and existing published snapshots are preserved.
Personnel sources pair their compiled assignment type with the existing experimental `AssigneeResolverInterface`; its execution context and entity contracts remain experimental.
Plugin resolvers receive the normalized request form during preview and execution, and unavailable dynamic participants prevent submission or stage entry.
Instance details use actual node execution outcomes rather than inferring approval policy from individual task votes.
Approval source, mode and compatibility registrations, request form data sources and release actions reject duplicate names and return disposers.
Removing a source or mode also removes its compatibility registrations; reinstalling requires explicitly opting in again.
Template `create(types, { translate })` receives the current interface translation context; authored labels and form fields are translated only when creating the document.
Workflow viewers require `user:read` to resolve approvers; no user write privileges are granted.
Saved draft revisions can be published through `POST /api/_action/workflow/draft/{id}/publish` with `expectedRevision` and `publisher`.
Publication requires `workflow:publish` plus the target privileges, preserves the original document in an immutable snapshot, and deduplicates repeat publication of the same revision and target.
Deleting a draft preserves its published versions and reserves its identifier against reuse.
`GET /api/_action/workflow/publishers`, `/api/_action/workflow/releases`, and `/api/_action/workflow/release/{id}` expose targets and snapshots to `workflow:read` users.
Business targets implement `WorkflowPublisher` and register the `shopwell.workflow.publisher` tag; target persistence runs in the snapshot transaction, while instance execution starts separately.
The built-in `core:approval` target executes approval stages and ordered conditional branches based on request fields: each approval stage supports single-person, all-must-approve, or first-decision rules.
The first-decision rule ends on either agreement or rejection; remaining tasks close automatically once the outcome is decided.
Unavailable configured reviewers block publication or stage entry instead of silently reducing the required reviewer set.
Unsupported nodes and modes are rejected at publication.
Request forms are configured on the start node with `formFields` and support text, amount, number, date and single-select fields, required checks and published schema snapshots.
Text fields can declare a `dataSource`; `core:currency` provides configured currency names and ISO codes through a standard selection control and validates submitted membership on the server.
Extensions supply read-only options through `Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowFormOptionsEvent` and can register request action components using the start node's `requestAction` identifier.
Published node and connection identifiers are limited to 64 characters so they can be recorded by the runtime; editable drafts retain their 128-character limit.
Invalid runtime identifiers and approval configuration expose structured diagnostics with code, document path and node identifier in `errors[].meta.parameters.violations`.
`GET /api/_action/workflow/request-catalogue` requires workflow read and request start permissions; `GET /api/_action/workflow/form-options/{source}` exposes only source options to workflow readers.

Position assignees (`core:positions`, compiled as `position`) resolve selected positions within the initiator's primary department at stage entry and use the configured position order for sequential approval.
Inactive positions, unavailable members and missing decision privileges block publication or entry instead of reducing the participant set.
Workflow designers require `position:read` to select and display position names; installations of new default manager roles include that privilege.
Structured branches support nested `and`/`or` condition groups, with at most six nesting levels and 100 conditions per branch; empty groups are rejected.
Conditions reference `request.<field key>` with a bound `fieldType`, evaluate in configured order and use the default branch when no condition matches; amount comparisons use decimal arithmetic.
Generic execution conditions retain `${form.<field key>}` references, explicit comparisons and `&&`/`||` grouping, with at most 64 nested parentheses and Symfony ExpressionLanguage's native parsing and AST depth limits.
Very long flat logical chains can exceed the library's depth limit and are rejected during validation.
Changing a referenced field type requires explicitly rebinding the condition before publication.
Invalid values, unavailable fields and unsupported operator/type combinations are rejected before execution; unselected branches create no approval tasks.
The Administration exposes `workflowRequestFormService.getFields(config, defaultReasonLabel)` for release consumers to read request field definitions without importing designer internals.
Core workflow instance details expose ordered `approvalStages` with immutable stage names, descriptions, rules and participants from the published snapshot, stage outcomes and actual tasks.
Future stages remain visible without creating tasks or todos before entry; rejection and unselected branches mark remaining stages as skipped.
Instance details expose `plannedPath` for the route selected by frozen request values and `selectedPath` for the visited prefix, with node and edge identifiers matching the instance graph.
`approvalTasks` includes all actual tasks, including those closed without a decision; `tasks` remains the current-node action list.
The Administration `workflowReleaseActionService` registers target-specific actions without coupling the designer to a runtime.
System installation creates missing workflow manager and participant roles with their corresponding menu selections; `workflow:roles:install` provides the same create-only operation for development installations without assigning users or overwriting existing roles.
Business extensions can add default privileges and menu keys through `WorkflowDefaultRolesEvent`.

Approval nodes accept an optional `operations` list, frozen at publication; omission enables transfer, add-sign and return, while an empty list disables them.
Task `availableActions` and locked operation admission use the same node policy.
Plugins declare names through `ApprovalOperationRegistrationEvent`, restrict eligibility through the read-only `ApprovalTaskActionsEvent`, and handle their operation through `ApprovalTaskOperationExtension` at `POST /api/_action/workflow/task/{taskId}/operation`.
Core checks ownership, active node and round before dispatch and records the operation name and comment after success; plugin payloads are not automatically logged.
The Administration `workflowApprovalService.registerOperation({ name, labelKey, actionComponent })` returns an owned disposer; custom action components receive `taskId` and `isLoading` and emit `completed` to refresh the detail.

Pending approval tasks support `POST /api/_action/workflow/task/{taskId}/participation` with an `action`, `targetUserId` and required `comment`.
`transfer` creates a replacement task at the same approval position; `add-sign` adds a parallel mandatory reviewer whose rejection vetoes the stage, independently of its original approval mode.
Both operations require task ownership and `workflow_task:decide`, validate the target's active organization membership and approval permissions, and retain a distinct operation in task history.
Plugins can observe or replace participant operations through `ApprovalTaskParticipationExtension` within the instance transaction; external side effects must be queued through `WorkflowEventQueue` until commit.
Task projections expose `availableActions` and `requiredReviewer`; history may contain `transfer` and `add-sign` decisions with a `target` participant, in addition to approval votes.

### Workflow approval for finance transfers

Finance transfer requests require a published workflow selected through the finance list's approval settings.
The transfer template starts with department position approval, adds finance review for non-CNY currencies or CNY amounts above 5,000, and copies recipients after approval.
Select approval positions, the finance reviewer and copy recipients before publishing; the template does not assign authority to the default employee position.
Transfer previews apply the booking product and currency checks without creating payment or workflow records.
The request supplies the workflow's `amount`, `currency`, `recipient`, `paymentAccount` and optional `reason` fields from server-side financial data.
The published revision and encrypted recipient snapshot remain fixed for that transfer.
Approval and payment statuses are separate: final approval enables an authorized finance operator to execute the transfer, while channel processing, failure and return remain payment outcomes.
Unbound, pending, rejected or unavailable approvals cannot be submitted through the transfer endpoint.
Generic entity writes and state transitions cannot replace the approval process or alter the reviewed financial data.
The Admin API provides `GET` and `PUT /api/_action/payment/transfers/workflow`, `GET /api/_action/payment/transfers/{id}/approval`, and `POST /api/_action/payment/transfers/{id}/approval/cancel`.
Runtime integrations implement `WorkflowRuntime` and register `shopwell.workflow.runtime` using their publication target name.
Business bindings and instance creation commit together before runtime events are dispatched.
The built-in `core:approval` publisher and runtime are part of the main system and work with OA disabled.
Workflow > My tasks provides pending tasks, completed tasks and initiated requests, with participant-scoped details and decision actions.
Core runtime resources use `workflow_instance:access`, `workflow_instance:start` and `workflow_task:decide`; designer publication continues to require `workflow:publish`.
Core database migrations create Workflow drafts, releases, transfer bindings, execution tables and state machines for both installation and updates.

Transfer approval configuration now reports unavailable reviewers and rechecks their live account and decision privileges before binding a release or accepting new requests.
Transfer lists load approval state and current reviewers in one batch through `POST /api/_action/payment/transfers/approvals`, requiring `payment_transfer:read`; approval detail links remain participant-scoped.
Transfer creation previews the expected approval path through `POST /api/_action/payment/transfers/approval/preview`, requiring `payment_transfer:create`.
The read-only preview uses the configured release and shared routing rules, assumes all reviewers agree, and creates no recipient, transfer, instance or task.
Runtimes can supply previews through `WorkflowRuntimePreviewEvent` without changing the execution adapter contract; submitting still revalidates live prerequisites.
Runtime extensions can contribute read summaries through `WorkflowRuntimeStatusesEvent` and recheck release prerequisites through `WorkflowReleaseCheckEvent` without changing the execution interface.

### After-sales refund destination

Administration refund confirmations default to the original payment sources and can instead credit a registered customer's wallet.
The approve, receive and refund actions accept optional `refundDestination` (`original` or `wallet`); the first payout attempt fixes this value on the return.
Wallet payouts also reduce the collected payment's refundable amount and use the return number for idempotency.
Guest returns support the original payment sources only.

### Free growth alongside paid membership

Customers continue earning tier points during paid membership.
The effective tier is the higher of the earned tier and the valid paid tier; expiry falls back to the earned tier immediately when read.
Paid tier qualification is available as `loyalty_account.paidTierId`, independently of the effective `tierId`.
Existing running subscriptions are migrated automatically.

### SMS provider extension contract

SMS providers extend `Shopwell\Core\System\Sms\Provider\AbstractSmsProvider` and register with `shopwell.sms.provider`.
Credential and connection fields use `SmsConfigField`; the Administration renders them and enabled accounts are validated when written.
The provider metadata endpoint includes `optionDefinitions`.
Template sending and template approval are independent capabilities under `Provider\Audit`.
The development baseline removes the former gateway factory registration and uses `Routing\SmsGatewayResolverInterface` for routing.
The `SelectSmsGatewayExtension` extension can reorder or remove eligible accounts.


### PayPal and Stripe integrations moved to SwagPayments

PayPal and Stripe are provided by the separate `swag/payments` plugin and are no longer bundled with Core.
Install and activate `SwagPayments` and configure your merchant accounts to use their checkout, refund, payout and notification capabilities.
The SDK dependencies, payment method initialization, icons and Administration translations are owned by the plugin.
Integration classes now use `Swag\Payments\Integration` instead of `Shopwell\Core\System\Payment\Integration`.
The Core installation baseline seeds only Alipay and WeChat Pay, and the first-run wizard no longer installs the legacy `SwagPayPal` plugin.
The generic recipient readiness method is named `PaymentTransferRecipientService::markProviderAccountReady()`.


### Blog publishing and content layouts

New Blog entries default to drafts (`active=false`).
Publish an entry by setting `active=true`; a future `publishedAt` schedules its publication.
Blog entries reuse system tags and support native `blog_content_layout` assignments with `rootSource=blog`.
Tags expose their related entries directly through the native `blogs` association.
The Blog installation baseline provisions shared article, video, and listing layouts automatically.
The repair command `bin/console content:blog:provision-layouts` remains available.
The type-specific configuration keys are `core.content_system.default_blog_post_content_layout` and `core.content_system.default_blog_video_content_layout`, with `core.content_system.default_blog_content_layout` as the shared fallback.
The shared homepage/category listing default is configured through `core.content_system.default_blog_listing_content_layout` and uses `rootSource=none`.
Blog layout associations extend `content_layout` through `extensions.blogContentLayouts`.
Explicit Blog assignments take precedence over legacy standalone body layouts, which remain supported.
Register custom types through `BlogTypeRegistry::addType($type, $template, $schemaType)` instead of type handlers.
`GET /api/_action/blog/types` lists registered type names.


### MCP servers run on `mcp/sdk` 0.9 and `symfony/mcp-bundle` 0.15

Shopwell now requires `mcp/sdk` 0.9 and `symfony/mcp-bundle` 0.15.
Plugins and bundles that register MCP tools are affected in these points:

* A tool that declares an `outputSchema` has its `structuredContent` validated against it.
  A result that does not match is sent as an error result with `isError: true`.
  Return `new \stdClass()` for an empty object, since `[]` is sent as an array.
* A tool whose `inputSchema` has a `properties` member that is not an object, or a `required` member that is not a list, is rejected when it is registered.
* `#[CompletionProvider]` no longer takes `providerClass`; pass the class to `provider` instead.

The SDK classes for an OAuth proxy, client registration and OIDC discovery were removed, see the [mcp/sdk 0.9.0 changelog](https://github.com/modelcontextprotocol/php-sdk/blob/v0.9.0/CHANGELOG.md).
The MCP endpoints match the `Origin` header of browser clients exactly, including scheme and port.
They accept the origins of `APP_URL` and of every sales channel domain.
A loopback origin, for example the MCP Inspector on `http://localhost:6274`, is accepted only for a shop on a loopback host such as `localhost` or `*.localhost`.
Clients without an `Origin` header, such as CLI clients and desktop apps, are not affected.
Allow further origins with `shopwell.mcp.allowed_origins`.
An entry is an exact origin, an origin with `:*` for any port, or `*` for any origin:

```yaml
shopwell:
    mcp:
        allowed_origins:
            - 'https://chat.example.com'
            - 'http://localhost:*'
```

The list can also come from an environment variable, for example `allowed_origins: '%env(csv:MCP_ALLOWED_ORIGINS)%'`.
A rejected origin is logged as a warning in the `mcp` channel.

### Collection class for import/export files

`import_export_file` entities are now loaded into an `ImportExportFileCollection` instead of a generic `EntityCollection`.
Replace `EntityRepository<EntityCollection<ImportExportFileEntity>>` annotations with `EntityRepository<ImportExportFileCollection>`.

### Promotion line items store the discount value in the cart currency

`payload.value` of promotion line items with an absolute, fixed or fixed unit discount now contains the value in the cart currency, using the advanced price of the currency or the currency factor.
Orders placed before this change still contain the default currency value.
Extensions and headless frontends that convert `payload.value` into the cart currency themselves must remove that conversion.

## Administration

### Removed the empty Shopwell Payments navigation module

The `sw-payments` placeholder module, `sw.payments.index` route and `sw-payments.*` privilege mapping were removed.
Extensions must register their own functional payment pages instead of attaching navigation to this placeholder.
The `sw-payment` payment orders and refunds module remains available.

### Payment route filters

Payment order filters and columns read `activeAttempt.channelTechnicalName` and `activeAttempt.providerTechnicalName`.
Refund channel filters read `paymentAttempt.channelTechnicalName`, so they retain the route used by the refunded payment attempt.
The `payment_order.viewer` and `payment_refund.viewer` mappings include `payment_attempt:read`.

### Administration navigation layouts

Administrators can choose sidebar, top, or combined navigation under My profile > User interface and apply the choice by saving their profile.
The preference is stored per user under `core.adminNavigationLayout`; smaller screens continue using the navigation drawer.
All layouts use the configured menu order, role restrictions and registered module navigation.
Top and combined layouts place the existing page search and toolbar actions in one compact header.
Sales channels use their own navigation area instead of appearing in every module sidebar.
Top navigation is the default for accounts without a saved layout preference.
Workflow is a separate top-level menu with My approvals and Workflow management.
Publication history is a management tab; participants choose an available workflow through Start request.
Generic requests use the newest release per workflow; business modules can restrict their request actions to the enabled release through `WorkflowRequestCatalogueEvent`.
Transfer requests expose only the finance-enabled release to users with transfer creation permission.
Articles, categories, authors and comments are grouped under Content > Articles.
Payment orders and refund orders are direct entries under Finance, alongside transfer requests and wallet transactions.

### Return settings per sales channel

Return rules support sales channel overrides and inheritance from the global configuration.
Return reasons have a separate settings entry and remain shared across sales channels.
The return rules page uses the standard `system.system_config` permission; reason management continues to use the return permissions.

### Role-based menu assignments and separate user management

User management now uses the `sw-user` module and `user.*` privileges.
Roles, business permissions and menu configuration use `sw-permissions`, `permissions.*` and `admin_menu.*` respectively.
Role details combine explicit menu selection with the existing permission matrix and dependency handling.
Role details now display special operations alongside their owning page and show the pages sharing a capability.
Modules can declare an optional `resource` on an operation privilege role to associate it with a business permission key; this metadata does not grant access.
The verified role write endpoint accepts a complete `menuIds` selection, saved atomically with role privileges.
Menu selections do not grant page or API access, and an empty selection hides navigation.
Role menu selections combine across roles; a role without selected menus contributes no navigation.
The visible menu response includes `allowUnmanaged` to control client declaration fallbacks.
Menu-side role assignment and the `/_action/admin-menu/assign-roles` endpoint were removed.

### Configurable administration menus

Core and plugins declare nested menu defaults in `Resources/config/admin-menu.xml`, using readable stable IDs and literal localized titles.
The container validates and compiles XML; plugin files are discovered automatically for active plugins.
Translated `customFields` are available on `admin_menu_item` and `admin_menu_item_translation`, and XML can provide per-language values.
Unmodified stored defaults follow declarations; administrator customizations remain preserved.
Frontend navigation bindings supply routes and privileges, while XML owns labels, grouping and order.
The visible and route-whitelist endpoints accept `locale` for interface-language titles.
Plugins can subscribe to `AdminMenuVisibilityEvent` to hide declared entries and their subtrees based on runtime conditions; role selections and route/API permissions still apply.
See [Administration menus](technical-docs/03-extensibility/10-admin-menu.md) for declarations and extension examples.

### Organization workspace

Organizations and positions are under System, using the `sw-organization` and `sw-position` modules and the `sw.organization.*` and `sw.position.*` routes.
The organization workspace displays organizations as independent roots and units nested below them.
The index, create and detail routes reuse this workspace; the detail route accepts `unitId` and `parentId` query parameters to restore unit selection and creation context.
Extensions can use the sidebar and node action slots of `sw-organization-unit-tree`, which receives organizations and units.
The detail blocks use the `sw_organization_detail_` prefix.
New organizations open `sw.organization.create`; new units open an unsaved entity in the detail form with the selected parent prefilled.
Changes are persisted through Save and reverted through Cancel using the entity repository.

### Blog editor and extension points

The Blog editor provides a wide content canvas, a compact settings sidebar, and separate Content, Layout and SEO tabs.
Register custom editors through `registerBlogType($type, { component, label, icon })`; existing lazy editor loaders remain supported.
Named editors use the Administration component factory, so component extensions apply.
Plugins can extend `sw-blog-detail-content`, `sw-blog-detail-settings`, and `sw-blog-detail-tabs` through the standard component section and tab extension APIs.
Tags use the system tag selector within the editor and do not add a navigation item.
Blog navigation now groups content, categories, authors, and comments under a localized menu folder.
Category management uses one workspace with a persistent category tree and an inline editor with General, Content, Layout, and SEO tabs.
The category tree follows the system menu tree with drag handles, positional drop previews, sibling and child creation, and expand/collapse actions.
Category search uses the standard page search bar, retains matching ancestor paths, and restores expanded branches when cleared.
Root creation uses the page actions; sibling and child creation use the node action menu.
New categories open an unsaved full form in the same workspace and are persisted only through Save.
New authors open the `sw.blog.author.create` route and use the full detail form; records are persisted only through Save.
Categories support parent and child relationships, drag and drop ordering, and menu actions for moving categories.
Tree changes persist immediately after resolving unsaved editor changes.
Moving a category into itself or its descendants is rejected, and categories with children must be emptied before deletion.
The Layout tab displays the effective global layout and supports copying it or restoring the shared default.

### Today dashboard statistics

The Administration homepage now shows today's product sales, successful refunds, wallet top-ups, membership activations, and hourly sales in the operator's timezone.
`GET /api/_admin/dashboard/today` provides these statistics in the system currency, with comparisons to the same local time yesterday for all four metric cards.
The endpoint derives its timezone from the authenticated user profile.
Product sales exclude wallet top-ups and membership purchases, and duplicate successful payment attempts do not create additional sales.
Posted wallet top-ups are reported separately with amount and count, subject to wallet transaction read permission.
Membership activations count distinct customers by successful activation time, including renewals, subject to membership purchase read permission.
Wallet amounts use current exchange factors and are accumulated in system currency minor units.
The former order-amount endpoint has been replaced by the today endpoint.


### Deprecated the sales channel lists of `sw-settings-search-live-search` and `sw-newsletter-recipient-list`

The `salesChannels` data property, the `salesChannelRepository` computed property and the `fetchSalesChannels()` method of `sw-settings-search-live-search` are deprecated and will be removed without replacement.
The `salesChannelFilters` data property of `sw-newsletter-recipient-list` is deprecated and will be removed without replacement.
Their sales channel selects load their options themselves, so with the `V6_8_0_0` feature flag both components no longer load sales channels when they are created.

### Meteor apps get their own path in the Administration watcher

An extension that ships an Administration entry and a `meteor-app` used to get both dev servers under `/_internal_ext/<technical-name>/`.
Both servers shared one proxy path, so the Administration only reached one of them.
The watcher now serves meteor-apps under `/_internal_app/<technical-name>/`, also for extensions without an Administration entry.
The Administration reads the new URL from `sw-plugin-dev.json`, so you only need to adjust tooling that calls the old URL of a meteor-app directly.

### The Administration watcher applies template edits without a page reload

While `composer watch:admin` runs, saving the Twig template of a component or of a component override updates that component on the page and keeps its state.
Components that extend it update as well.
The watcher still reloads the page when it cannot apply an edit in place:
for a Twig override of a component without a Twig template, for a template whose content another template file has as well, or for a component that has not been rendered yet.

With the `ADMIN_VITE_8` feature flag, the watcher also applies script edits of native `.vue` components and `.override.vue` files of extensions without a page reload.
This includes edits of the files such a component imports.
The watcher remounts the edited component and the components an edited override changes.
It reloads the page for edits it cannot apply in place:
edits that change the blocks of a component, edits of a component built with `Component.extend()` or `Component.override()` or of a component that is not on the page, and updates that fail to render.

### Experimental: Vite 8 and HTTP/2 for the Administration behind the `ADMIN_VITE_8` feature flag

The new `ADMIN_VITE_8` feature flag builds the Administration and the Administration code of extensions with Vite 8.
Vite 8 becomes the default with Shopwell 6.8, so you can use the flag to test your extensions now:

```bash
ADMIN_VITE_8=1 composer watch:admin
ADMIN_VITE_8=1 composer build:js:admin
```

With the flag, building the Administration requires Node.js `^20.19.0 || >=22.12.0`.
The `postinstall` script of the Administration installs Vite 8 into `build/vite-8`.
If your CI restores the Administration dependencies from a cache without running that script, also run `npm ci` in `build/vite-8`.

With the flag, the watcher serves all files over HTTP/2.
Browsers only use HTTP/2 over HTTPS, so put HTTPS in front of the watcher:

* Run `shopwell-cli project proxy up` and open `https://admin-watch.<project>.shopwell.local`.
* Or set `SHOPWELL_ADMIN_HTTPS_CERTIFICATE_FILE` and `SHOPWELL_ADMIN_HTTPS_KEY_FILE` to a certificate and its key, for example created with [mkcert](https://github.com/FiloSottile/mkcert).
  Relative paths are resolved from the project root.

With the flag, the watcher serves the Administration code and the meteor-apps of extensions through its own port.
The separate `Extensions` process and the port per extension are gone.
The `vite.config` of an extension can no longer change the address of its dev server.
The watcher ignores `server.port`, `server.host`, `server.ws` and the connection options of `server.hmr` (`protocol`, `host`, `port`, `clientPort`, `path` and `server`) there.

### `sw-chart` loads only the chart locale it needs

`sw-chart` now loads the ApexCharts locale of the current Administration language on demand instead of importing all 33 locales up front.
English uses the locale built into ApexCharts and loads nothing.
The chart renders once the locale has loaded.
`createdComponent()` and `loadLocaleConfig()` of `sw-chart` now return a promise.
If your override reads `localeConfig` after calling the base method, await it:

```js
async loadLocaleConfig() {
    await this.$super('loadLocaleConfig');
    // this.localeConfig is set here
}
```

## Storefront


### Skip link for listing filters

Category, landing and search pages with a filtered product listing now offer a "Skip to products" link in the skip-to-content bar, so keyboard users can jump past the filters to the products.
The link targets the new `id="product-listing"` on the product listing wrapper, including the `Sw:Product:Listing` component, and is only shown from the `lg` breakpoint, where the filters are not collapsed into the off-canvas button.
Themes that override the `product_listing` block in `storefront/component/product/listing.html.twig` without `parent()` must keep `id="product-listing"` on their wrapper, otherwise the link has no target.
`storefront/component/skip-to-content.html.twig` has a new `productListing` parameter (default `null`) and the new block `component_skip_to_content_product_listing`.

## API

### Installed extensions tell whether they can be removed

Extensions returned by `GET /api/_action/extension/installed` have a new `allowRemove` flag, also available as `ExtensionStruct::isAllowRemove()`.
It is `false` when Shopwell can't delete the extension's files, for example for extensions that Composer installed outside `custom/plugins`, and for extensions that are only available in the store.
Check it before calling `POST /api/_action/extension/remove/{type}/{technicalName}`.


## App System

### App MCP prompt and resource responses

App MCP prompt responses are now decoded as JSON arrays of messages with a `user` or `assistant` role and `content`, for example `[{"role":"user","content":"Hello"}]`.
App MCP resource responses must be JSON objects containing a `text` string or a base64-encoded `blob` string, for example `{"uri":"my-app://status","mimeType":"application/json","text":"{\"ok\":true}"}`.
Plain-text resource responses are no longer accepted; wrap the content in a JSON object.
Resources use the URI declared in `mcp.xml`; a non-empty `mimeType` in the response takes precedence over the configured MIME type.
Invalid JSON, malformed messages or resource contents, and `{"success":false,"error":"..."}` responses produce MCP errors with details for the client.

### Handling, media and workflow control flow

Approval workflows support handling tasks with an explicit completion action and preserve completion comments in task history.
Input schemas support `media`, `mediaList` and `images` values; submitted media is checked through the Media repository and `media:read` privilege at the point of submission.
Returning a task may name a published approval stage; the next resubmission starts at that stage while retaining prior rounds.
Parallel and join nodes create branch-scoped execution records and wait for every incoming branch before continuing.
Working-time deadlines count Monday to Friday from 09:00 to 18:00 UTC when `workingTime` is enabled; elapsed deadlines remain the default.
Overdue reminder events include an optional configured `escalateTo` user ID for delivery extensions.

### Workflow integrations and script nodes

Action, notification and subprocess nodes dispatch `WorkflowAutomationActionEvent` with a pinned action and input map so connectors and data actions can run through one transaction-aware extension seam.
Script nodes evaluate bounded Symfony expressions with only `form` and `round` values and persist their result in node output; PHP and service access is unavailable.

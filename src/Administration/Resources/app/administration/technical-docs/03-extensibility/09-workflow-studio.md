# Workflow Studio extensions

Workflow Studio uses Vue Flow only to render the canvas.
The document, editing history, commands, events and extension lifecycle live in the Vue-independent `CanvasEditorSession<WorkflowDocument>`.
The module registers services and components through the standard `Shopwell.Service()` and `Shopwell.Component` APIs, following Experience Studio's separation of registration, presentation, state and UI components.

## Shared canvas editor

The shared editor lives in `src/core/canvas-editor` and `src/app/component/structure/sw-canvas-editor`.
It does not import Workflow, approval services or executable node specifications.
`CanvasDocument` requires nodes with identifiers and connections with identifiers, sources and targets; the owning domain supplies additional fields.
Construct `CanvasEditorSession<YourDocument>` and install `CanvasExtension<YourDocument>` definitions directly for editors outside Workflow.
The session owns commands, selection, bounded history and extension resources.
Malformed graph shapes and duplicate identifiers are rejected before committing a command, while disconnected nodes, dangling connections and cycles can remain in editable drafts.

`sw-canvas-editor` receives an engine-independent `CanvasRenderGraph` projection.
A node projection supplies its position, data, optional registered Shopwell component and input/output ports with optional sides.
Register visual components through `Shopwell.Component.register()`; neither the projection nor plugin contexts expose Vue Flow types or stores.
The owning domain selects a layout and supplies positions, and can pass a `CanvasConnectionRouter` through `route-connection`.
The surface emits `select-node`, `select-connection`, `move-node`, `connect`, context-menu events and measured node sizes.
Movement only previews coordinates inside the renderer; the owner accepts the final intent through a document command or the surface restores its supplied position.
Setting `allow-edit` to false blocks move/connect intents and discards a current movement preview, while selection remains available.

The shell has optional `toolbar`, `sidebar-start`, `sidebar-end`, `footer`, `overlay` and `viewport-controls` slots.
Node content, decorations, node tools and connection tools have separate slots, so an editor can provide its own controls without modifying the surface.
The `node-tools` slot uses the official `@vue-flow/node-toolbar` component to position controls outside the scaled graph.
Tools appear on hover, keyboard focus or selection; Workflow supplies the edit button and contributed node actions.
No empty sidebar is rendered without a contribution.
`sw-canvas-contributions` renders a session's actions, components and dialogs using Shopwell components and snippets.
The shell does not persist documents, choose templates, validate business rules, publish or execute a workflow.
Workflow owns those responsibilities and adapts its graph through `sw-workflow-studio-canvas`.

## Register a Workflow capability bundle

`workflowStudioService.register()` installs one independently owned bundle of node presentations, editing strategies, templates, layouts, field providers, diagnostics and editor extensions.
The returned disposer unregisters the bundle, including its contributions in open editors.
Registration fails on collisions and rolls back contributions already installed by the failed bundle.
Approval is registered separately from the basic Workflow and condition capabilities.
These are Workflow capabilities, not requirements imposed on the shared canvas.

## Register an editor extension

Register definitions during Administration initialization, before an editor is opened.
Each editor runs `setup` independently and owns its commands, event subscriptions and UI contributions.
The disposer returned by `register` removes the definition from future sessions and uninstalls its contributions in open sessions.
Use namespaced identifiers to avoid collisions; duplicate registrations fail instead of replacing another extension.

```ts
Shopwell.Service('workflowExtensionService').register({
    name: 'example:review-tools',
    setup(context) {
        context.commands.register({
            name: 'example:rename-review',
            canExecute: (state, payload) =>
                typeof payload === 'string' && state.document.nodes.some(node => node.id === state.selection.nodeId),
            execute(document, payload, commandContext) {
                const id = commandContext.getState().selection.nodeId;
                const node = document.nodes.find(item => item.id === id);
                if (node) {
                    node.config = { ...node.config, label: String(payload) };
                }
                return document;
            },
        });
        context.ui.registerAction({
            name: 'example:rename-review',
            position: 'node-menu',
            labelKey: 'example.renameReview',
            command: 'example:rename-review',
            payload: 'Review order',
        });
        context.events.subscribe('node.click', event => {
            // Call event.preventDefault() to replace the default editor interaction.
        });
        return () => {
            // Dispose external subscriptions, observers or other resources owned by this session.
        };
    },
});
```

## Commands and lifecycle

`getState()` returns detached JSON snapshots; mutating a snapshot never edits the authoritative document.
A document command returns its complete edited document, records at most one history entry, rechecks edit access and emits `document.changed` to the normal save and diagnostics pipeline.
View commands declare `mutatesDocument: false` and must return the unchanged document.
`commands.execute('core:undo')` and `commands.execute('core:redo')` use the same access and interception checks as edits.
`command.beforeExecute` is ordered and cancelable; nested command execution during command handling is rejected.
Command failures leave the document and history unchanged and are reported through the session error callback.
Errors in event handlers and disposers are isolated so other extensions continue to run.

Built-in commands are `core:insert-node`, `core:update-node`, `core:remove-node` and `core:load-template`.
Node-specific edit semantics remain in the registered editing strategies, and template creation remains in `workflowTemplateService`.
Node configuration dialogs stage local changes and submit one command on confirmation.
Canceling the dialog discards its staged changes.
The generic session itself only reserves `core:undo` and `core:redo`; node editing and template commands belong to the Workflow extension.

Uninstalling an extension removes its registrations and subscriptions and invalidates retained contexts, including contexts passed to command callbacks and UI components.
Closing an editor disposes every installed extension.
Switching to an externally replaced document starts a new session.
Registering an extension is a trusted in-process API, not a JavaScript sandbox or a replacement for server-side ACL checks.

## UI positions and semantic events

| Contribution | Positions |
| --- | --- |
| Actions | `toolbar`, `node-menu`, `edge-menu`, `node-picker` |
| Components | `canvas-overlay`, `node-decoration`, `panel`, `dialog` |

Actions use `labelKey`, an optional `icon`, `order`, `when`, a command name and an optional static payload or payload factory.
The command's `canExecute` controls disabled state, and edit permissions apply automatically unless the command explicitly declares itself a view command.
Components are registered Shopwell component names and receive `context`, `target`, a detached `node` and a detached `connection`.
Contributions are ordered by ascending `order`; equal orders retain registration order.
A dialog contribution follows its current `when` condition; temporarily hidden dialogs retain their props until closed or unregistered.
A dialog contribution can provide `titleKey` and is opened with `context.ui.openDialog(name, props)` and closed with `context.ui.closeDialog()`.

Semantic interaction events are `node.click`, `node.contextmenu`, `connection.click`, `connection.insert`, `connection.contextmenu`, `canvas.click` and `canvas.contextmenu`.
They carry definition identifiers instead of Vue Flow objects and support `preventDefault()`.
Subscriptions run in descending priority order and return an optional early-unsubscribe disposer; extension teardown always removes the subscription.
State notifications include `selection.changed`, `document.changed`, `command.executed`, `session.changed` and `session.destroyed`.

## Architecture references

[diagram-js](https://github.com/bpmn-io/diagram-js) separates its event bus, command stack and context actions.
[LogicFlow](https://github.com/didi/LogicFlow) and [X6](https://github.com/antvis/X6) expose plugin lifecycles independently of graph rendering.
Workflow Studio adopts those boundaries while retaining Shopwell's service registration, component extension and translation conventions.
New business nodes continue to register their definition, presentation, editing strategy and runtime behavior separately.

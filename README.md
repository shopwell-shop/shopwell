# Shopwell

Shopwell is an open-source commerce platform for building storefronts, managing products and orders, and extending business workflows.
The source code is available under the [Apache License 2.0](LICENSE).
Third-party copyright and license notices are collected in [NOTICE](NOTICE).

## Choose your starting point

| Goal | Starting point |
| :--- | :--- |
| Build a shop | Follow the development setup in [CONTRIBUTING.md](CONTRIBUTING.md). |
| Build an extension or theme | Use the extension model that fits your deployment below. |
| Contribute to the platform | Read [CONTRIBUTING.md](CONTRIBUTING.md), [AGENTS.md](AGENTS.md), and the [coding guidelines](coding-guidelines/). |

## Build your commerce experience

Shopwell combines a product catalog, cart, checkout, order management, and an Administration interface.
Experience Studio provides component-based content layouts.
Themes and API-based frontends can shape the shopping experience, while events and Flow Builder support custom workflows.

## Technology

| Part | Technology |
| :--- | :--- |
| Backend | PHP, Symfony, Doctrine DBAL |
| Storefront | Twig, Bootstrap |
| Administration | Vue, TypeScript |
| Data | MySQL or MariaDB, with the Shopwell Data Abstraction Layer |
| Integrations | Admin API, Store API, and Sync API |

Use the dependency manifests and development setup for the requirements of this development baseline.
English and Simplified Chinese are built-in languages.

## Extend Shopwell

| Approach | Use it for |
| :--- | :--- |
| Theme | Change Storefront presentation and templates. |
| App | Integrate services through APIs and webhooks. |
| Plugin | Add in-process PHP behavior through supported extension points. |
| Headless frontend | Build a shopping experience with the Store API. |

Read the [architecture decisions](adr/) and [coding guidelines](coding-guidelines/) before changing an extension point.

## Develop locally

Follow [CONTRIBUTING.md](CONTRIBUTING.md) to install dependencies and configure a local development environment.
The [Shopwell CLI](https://github.com/shopwell-shop/shopwell-cli) provides project development tooling.
Use the URLs and credentials configured for your own environment.

## Work with coding agents

Agents can inspect the [project instructions](AGENTS.md) and [development skills](.agents/skills/) before proposing a change.
Ask for a small implementation using the existing extension points and meaningful tests.
Review and test generated changes before deploying them.
A coding agent working on project files has different access from an agent connected to a running shop through MCP.

## Contribute

Report reproducible issues through [GitHub Issues](https://github.com/shopwell-shop/shopwell/issues).
Follow the [contribution guide](CONTRIBUTING.md) for code, tests, documentation, and translations.
Report security concerns according to the [security policy](SECURITY.md).

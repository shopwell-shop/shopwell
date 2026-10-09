# Shopwell 6

Shopwell is an open-source e-commerce platform with API-first architecture exposing three distinct APIs (Admin, Store, Sync) alongside a built-in Twig-based storefront. It uses a custom Data Abstraction Layer instead of a traditional ORM, an event-driven extension system replacing decorators, and Flow Builder for business automation.

## Project Structure

```
shopwell/
├── src/
│   ├── Core/                     # Business logic & framework
│   ├── Administration/           # Admin UI
│   ├── Storefront/               # Frontend
│   └── Elasticsearch/            # Search integration
├── tests/                        # Test suites
└── bin/console                   # CLI commands
```

## Technology Stack

- **Backend**: PHP 8.2+, Symfony 7, Doctrine DBAL 4
- **Frontend Admin**: Vue 3, Pinia + Vuex, Vite, TypeScript
- **Frontend Storefront**: Twig, Bootstrap 5, Webpack 5
- **Database**: MySQL 8+ / MariaDB 10.11+
- **Search**: OpenSearch 2 / Elasticsearch 8
- **Cache**: Redis (optional), Symfony Cache
- **Testing**: PHPUnit, PHPStan, Jest, Playwright

## Shopwell Architecture

### NOT Standard Symfony/Doctrine
- **NO Doctrine ORM** - Uses custom Data Abstraction Layer (DAL)
- **NO QueryBuilder** - Use `Criteria` API instead
- **NO Doctrine Annotations** - Use `EntityDefinition` classes
- **NO Doctrine Repositories** - Use `EntityRepository` with DAL

### Extension Pattern Priority
1. **Prefer Events** - EventSubscriberInterface for most extensibility
2. **Use Decorators Only When** - Event timing doesn't fit

### Three Distinct APIs
- `/api/` - Admin API (full CRUD, admin operations)
- `/store-api/` - Store API (customer-facing, storefront)
- `/api/_action/sync` - Sync API (bulk operations)

## AI Skills

This repo ships Agent Skills under `.agents/skills/`, with `.claude/skills` as a symlink for Claude Code compatibility. Skills normally match their `description` against the task — best-effort and model-decided, **not guaranteed** — while skills with unattended CI twins require explicit invocation. The mandatory steps below are therefore stated here, in the always-loaded file, so they apply even when no skill is triggered.

### Definition of Done — mandatory for every change

Before you commit or hand work back:
- **Behaviour change ⇒ tests are required.** Admin JS/TS/Vue → follow `shopwell-admin-js`; PHP → `shopwell-phpunit-tests`. Twig-only template changes follow the rule below. Style-only, snippet/translation, and docs-only changes do not need tests; still add one when it is useful and follows an established pattern.
- **Twig-only template changes ⇒ never add PHP integration tests just to render or assert Twig output.** A direct render without a Storefront request can cache empty request-dependent Twig globals in the shared test kernel and break unrelated later tests. Run the Storefront Twig lint; use browser/acceptance coverage when the rendered behaviour needs testing.
- **Writing a PR title or description? → follow `shopwell-pr-hygiene`** — the Shopwell PR template is required, not a generic one.
- **Behavioural change, feature, deprecation, or config change? → check `shopwell-release-docs`** for RELEASE_INFO / UPGRADE entries. Put a blank line before and after every heading in those files; the Markdown renderer otherwise glues the heading to the previous paragraph.
- **Touching `.github/workflows/`, `.github/actions/`, or `.github/bin/`? → follow [`.github/AGENTS.md`](.github/AGENTS.md)** — a CI job must never report success without proving the work ran.
- **Commit with a conventional message incl. scope**, e.g. `feat(administration): …`.
- **After review feedback or CI failures**, create a follow-up commit; do not amend or force-push unless explicitly asked.
- **Lint every file you touched** per the File Linting table below.

When a task matches a skill, open `.agents/skills/<name>/SKILL.md` and follow it **before** implementing.

### Guidance Skills

- `shopwell-knowledge-capture` — saving durable knowledge; routing it to AGENTS, coding guidelines, README, ADR, skills, or local notes.
- `shopwell-change-scope` — root-cause analysis, boyscouting, and cleanup scope.
- `shopwell-release-docs` — release notes, upgrade notes, developer-facing changelog decisions.
- `shopwell-pr-hygiene` — PR templates, conventional titles, review follow-up commits.
- `shopwell-php-code` — PHP architecture, API schema, migrations, deprecations, BC-sensitive code.
- `shopwell-admin-js` — Administration JavaScript, TypeScript, Vue, ACL, Jest.
- `shopwell-phpunit-tests` — PHPUnit test structure, fixtures, feature flags, coverage, data providers.

Skills can have an optional unattended twin via [GitHub Agentic Workflows](https://github.com/githubnext/gh-aw) at `.github/workflows/<name>.md` + `.github/aw/<name>-policy.md`. Editing or compiling these workflows requires the `gh aw` CLI extension; the current pin lives in [`.github/aw/README.md`](.github/aw/README.md) → "Pinning".

To add a new skill (interactive or unattended), follow the checklist in [`coding-guidelines/core/agent-skills.md`](coding-guidelines/core/agent-skills.md).

## Subtree Guidance

- PHP/server code: use the `shopwell-php-code` skill when the task touches PHP architecture, API schema, migrations, deprecations, or BC-sensitive code.
- Administration JS/TS/Vue code: detailed guidance starts at `src/Administration/Resources/app/administration/AGENTS.md`; use the `shopwell-admin-js` skill for Admin coding rules.
- PHPUnit tests: use the `shopwell-phpunit-tests` skill.
- CI workflows, composite actions, and automation scripts: local rules in `.github/AGENTS.md`, rationale and examples in `coding-guidelines/core/ci-workflows.md`.
- More specific nested `AGENTS.md` files add local rules for their subtree.

## Coding Guidelines

**MANDATORY**: All code must follow the guidelines in `coding-guidelines/`.

## Snippets & Translations

Simplified Chinese (`zh`) is the second built-in language next to English. Write snippets the way Chinese e-commerce UIs phrase things: concise, product-oriented, no word-by-word translation of the English source. Keep terminology consistent per domain (Administration vs. Storefront) when adding or editing `zh` snippets.

Snippet files are named by language, not locale: `en.json` / `zh.json` in the Administration, `storefront.en.json` / `storefront.zh.json` in the Storefront. Locale-specific names such as `en-GB.json` or `storefront.zh-CN.json` are the legacy scheme; do not create them, and do not use them in tests, fixtures, or examples unless the test deliberately covers the legacy loader.

## File Linting

**MANDATORY**: All code must be linted according to the following table.

| File Type              | Check Command                 | Fix Command                                  |
|------------------------|-------------------------------|----------------------------------------------|
| **PHP** (.php)         | `composer cs`                 | `composer cs-fix`                            |
| **PHP** (types)        | `composer phpstan`            | N/A - must fix manually                      |
| **JS/TS/Vue** (Admin)  | `composer eslint:admin`       | `composer eslint:admin:fix`                  |
| **JS/TS** (Storefront) | `composer eslint:storefront`  | `composer eslint:storefront:fix`             |
| **SCSS**               | `composer stylelint`          | `composer stylelint:[admin\|storefront]:fix` |
| **Twig** (Storefront)  | `composer ludtwig:storefront` | `composer ludtwig:storefront:fix`            |
| **Snippets**           | `composer translation:lint`   | Manual fix required                          |
| **Prettier** (Admin)   | `composer format:admin`       | `composer format:admin:fix`                  |
| **GitHub Actions**     | `composer lint:actions`       | `composer lint:actions:fix`                  |

## Shopwell licensing guardrail

- Shopwell-owned code and publishable subpackages use Apache License 2.0.
- Project-owned package/composer manifests must declare `Apache-2.0`.
- Registered project-owned `LICENSE` files contain the standard Apache-2.0 text.
- Original upstream legal text is preserved verbatim in the root `NOTICE`; do not
  brand, shorten, delete, or move it into `LICENSE.upstream-*` files.
- Dependency lock files keep truthful third-party license metadata.
- Before commit, push, release, or sync completion, run:
  `../sync-upstream/bin/syncctl audit-license shopware` and
  `../sync-upstream/bin/syncctl audit-upstream-dependencies shopware`.
- Runtime code and workflows must not depend on `shopware/*`, `shopwarelabs/*`,
  `@shopware-ag/*`, or their GitHub repositories. A `shopwell-shop/*` Action
  dependency must have a matching entry in the control registry.
- Shopwell-owned npm and Composer dependencies must be published to a real registry
  before use and must use a normal stable version constraint. Git URLs, `github:`,
  GitHub archive/tarball URLs, commits, branches, Composer `dev-*`, `file:`, `link:`,
  and `workspace:` are not releases and must not be committed as consumer dependencies.
- A Git tag or successful publish workflow is not proof of release. Verify the exact
  version through the npm/Composer registry API before marking related work ready.
- If LICENSE, NOTICE, owned manifests, or upstream license inventory changes, update
  `../sync-upstream/config/repos.json` in the same task. A failed audit blocks completion.

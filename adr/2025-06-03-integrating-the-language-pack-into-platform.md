---
title: Integrating the language pack into platform
date: 2025-06-03
area: discovery
tags: [plugin, languages, language-pack, translations, crowdin]
---

## Context
The Shopwell Language Pack plugin enables us to distribute translations from Crowdin to Shopwell installations. While this was a convenient solution in the past, when most of our repositories and workflows were kept private. This approach is unnecessarily convoluted and cumbersome for developers and users, because changes to any snippet require multiple steps:

1. Translations are updated in CrowdIn (by the shopwell team and community)
2. Merging the changes from CrowdIn into `shopwell/translations` (automated, requires manual review)
3. Merging the changes from `shopwell/translations` into `shopwell/SwagLanguagePack`, which primarily just distributes json files (automated)
4. Publishing Language Pack releases to the store (automated, may require manual input)
5. Updating Language Pack installation
   - OnPrem: Manually updating the plugin in a Shopwell installation
   - Cloud: Updating the docker image dependency

This sequence also introduces overhead in maintenance and CI/CD.

### Background and Motivation
* The current setup was influenced by limitations of our previous GitLab-based workflow, where distribution via plugin was the most practical method. This workflow has been in place for ~6 years.
* Crowdin remains our single source of truth for all supported language snippets, and community members can contribute translations directly.
* `shopwell/translations` serves as an intermediary data layer to decouple core Shopwell repositories from Crowdin.
* The primary goal of this change is to reduce maintenance effort by replacing steps 3–5 with a single step, and removing `shopwell/SwagLanguagePack` from the workflow entirely. The new step should be targeting `shopwell/shopwell` instead.

## Decision
We will implement a new service in `shopwell/shopwell` (i.e. as part of the Shopwell platform) to download translations right from the [GitHub Repository](https://github.com/shopwell-shop/translations/) and manage them without the need of any extension.
Translations will be downloaded as JSON files (via admin user interaction or command execution) and stored on the local file system, just like existing platform snippet files.
In addition, we will provide new `bin/console` commands in `shopwell/shopwell` to manage installed languages, for example when building an image for deployment. The initial set of commands will look like this:

```bash
$ php bin/console translation

Available commands:
    install [translation] [--all, --locales]
    activate [translation] [--all]
    deactivate [translation] [--all]
    uninstall [translation] [--all]
    list
```

## Consequences
* The Language Pack plugin will be maintained for Shopwell versions < v6.8.0.
* Translations can be installed and updated on-demand, instead of waiting for platform/plugin release cycles.
* Translation versions will be mapped to platform version ranges.
* The general translations workflow remains the same. This has no impact on other extensions and their snippet files.
* For admin users the UX will improve: Available translations will now be listed directly in the administration and can be installed with a single click.

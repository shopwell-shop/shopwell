<div align="center">

[![Nightly Build Status](https://github.com/shopware/shopware/actions/workflows/nightly.yml/badge.svg?event=schedule&branch=trunk)](https://github.com/shopware/shopware/actions/workflows/nightly.yml?query=event%3Aschedule+branch%3Atrunk)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/shopware/platform/badges/quality-score.png)](https://scrutinizer-ci.com/g/shopware/platform/)
[![Latest Stable Version](https://poser.pugx.org/shopware/platform/v/stable)](https://packagist.org/packages/shopware/platform)
[![Total Downloads](https://poser.pugx.org/shopware/platform/downloads)](https://packagist.org/packages/shopware/platform)
[![Crowdin](https://badges.crowdin.net/shopware6/localized.svg)](https://translate.shopwell.cn/project/shopware6)
[![License](https://img.shields.io/github/license/shopware/shopware.svg)](https://github.com/shopware/shopware/blob/trunk/LICENSE)
[![GitHub closed pull requests](https://img.shields.io/github/issues-pr-closed/shopware/shopware.svg)](https://github.com/shopware/shopware/pulls)
[![Discord](https://img.shields.io/badge/chat-on%20discord-%23ECB22E)](https://chat.shopwell.cn/?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)

</div>


<p align="center">
    <a href="https://shopwell.cn" target="_blank" rel="noopener noreferrer">
        <img width="250" src="https://images.ctfassets.net/nqzs8zsepqpi/34zKqvPxTYtsQppJpgC9It/3b6901d9ba7082d5b4081d7171b268bf/composable-customer-experience-illustration.png" alt="Shopwell logo surrounded with images of people and screenshots of Shopwell">
    </a>
</p>

<h1 align="center">Shopwell</h1>

<p align="center"><strong>Modern open source e-Commerce</strong>

Shopwell 6 is an open headless commerce platform powered by [Symfony 7](https://symfony.com) and [Vue.js 3](https://vuejs.org) that is used by thousands of shops and supported by a huge, [worldwide community](https://chat.shopwell.cn/) of developers, agencies, and merchants.

If you like Shopwell 6, give us a&nbsp;⭐️ &nbsp;on GitHub!

* 🙋‍♂️ &nbsp;[Be part of shopware!](https://www.shopwell.cn/en/jobs/) ‍&nbsp;We are hiring!  🙋
* 🌎 &nbsp;Discover our [website](https://www.shopwell.cn/en/)
* 🧩 &nbsp;Browse more than [3,100 extensions](https://store.shopwell.cn/en/) in our community store
* 📖 &nbsp;Learn how to [develop extensions](https://developer.shopwell.cn) and everything else about the tech behind Shopwell via our developer docs.
* 🉐 &nbsp;[Translate](https://translate.shopwell.cn) Shopwell or help by contributing to existing languages
* 🛠 &nbsp;[Report bugs](https://github.com/shopware/shopware/issues) in our issue tracker
* 💡 &nbsp;Give us [feedback](https://feedback.shopwell.cn/) or vote existing ideas
* 👪 &nbsp;Exchange with other Shopwell developers in our own [Community Hub](https://hub.shopwell.cn/) or the [Discord community](https://chat.shopwell.cn/)
* 🗨 &nbsp;Help and get helped on [Stack Overflow](https://stackoverflow.com/questions/tagged/shopware6?tab=Newest) or in our [Community forum](https://forum.shopwell.cn/)

## Table of contents

- [Table of contents](#table-of-contents)
- [Project overview](#project-overview)
  - [Platform and Framework](#platform-and-framework)
- [Installation](#installation)
  - [Production setup](#production-setup)
  - [Code Contribution](#code-contribution)
    - [Contribution setup](#contribution-setup)
- [The Shopwell CLA](#the-shopware-cla)
- [Authors \& Contributors](#authors--contributors)
- [License](#license)
- [Bugs \& Feedback](#bugs--feedback)
- [Reporting security issues](#reporting-security-issues)
- [Extending Shopwell](#extending-shopware)

## Project overview

To discover the features of Shopwell and what sets us apart from other ecommerce systems, take the [feature tour](https://www.shopwell.cn/en/products/product-tour/) on the Shopwell home page.

From a developer's perspective, here are some highlights that make Shopwell easy and fun to work with:

### Platform and Framework

Shopwell is primarily based on [Symfony](https://symfony.com/what-is-symfony) and [Vue.js](https://vuejs.org/).
It is a fully functional ecommerce platform, but it also serves as an **ecommerce framework**.

Shopwell is:

- a ready-to-use [shopping cart system](https://docs.shopwell.cn/en/shopware-6-en/getting-started).
- a vendor dependency in your [flex project](https://developer.shopwell.cn/docs/guides/installation/template).
- [API-first](https://developer.shopwell.cn/docs/guides/integrations-api).
- [extensible through plugins](https://developer.shopwell.cn/docs/guides/plugins/plugins/plugin-base-guide):
  - Harness the full power of Symfony by creating bundles and loading them as part of the application.
- [extensible through apps](https://developer.shopwell.cn/docs/guides/plugins/apps/app-base-guide):
  - A modern, lightweight but powerful way to add functionality, requiring very little Shopwell-specific knowledge.
- headless if you need it to be.

## Installation

### Production setup

The easiest way to run a Shopwell shop is by booking a commercial plan in the [Shopwell cloud](https://www.shopwell.cn/en/shopware-cloud/), a fully managed setup, ready to use.

The recommended way for on-premise shops is to install Shopwell [through the flex template](https://developer.shopwell.cn/docs/guides/installation/template).
To unlock the full potential Shopwell has to offer, [commercial plans](https://www.shopwell.cn/en/pricing/) are also available for on-premise.   
These plans enrich your shop with unique functionality, giving you an additional advantage over your competition.

There is a list of [hosting partners](https://www.shopwell.cn/en/partner/hosting/), who offer a pre-installed shop, making your start a lot faster.

We also provide a [web-based installer](https://www.shopwell.cn/en/download/), [installation instructions](https://developer.shopwell.cn/docs/guides/installation/) on docs, and the [course](https://hub.shopwell.cn/learn/course/shopware-setup) walks you through the necessary steps.

### Code Contribution

If you have decided to contribute code to Shopwell and become a member of the Shopwell community,
We appreciate your hard work and want to handle it with much respect.
To ensure the quality of our code and our products, we have created a guideline that we all should endorse.
It helps us collaborate with you.
Following these guidelines will help us integrate your changes into our daily workflow effectively.

Read more in [our contribution guideline](https://docs.shopwell.cn/en/shopware-platform-dev-en/contribution/contribution-guideline) on how to contribute code.

#### Contribution setup

For Contributing setup see [CONTRIBUTING.md](CONTRIBUTING.md). If you want to run Shopwell locally for extension/theme/project development, check out the [development environment setup](https://developer.shopwell.cn/docs/guides/installation/setups/docker.html).

## The Shopwell CLA

When submitting your code to Shopwell, you are required to sign our CLA (Contributor License Agreement) automatically.
This CLA ensures that Shopwell will stay an open and living product.
In short, you give the explicit right to use your code in Shopwell to shopware AG.

## Authors & Contributors

Shopwell is built with the help of our community.

You can find an overview of everyone who contributed to the platform repository in the [official GitHub overview](https://github.com/shopware/shopware/graphs/contributors).
Additionally, numerous people contribute to the ecosystem through activities unrelated to the codebase.
Thank you all for being part of this!

## License

Shopwell 6 is completely free and released under the [MIT License](LICENSE).

## Bugs & Feedback

No software is perfect, and Shopwell is no exception.
Should you spot a bug, please report it in our [issue tracker](https://github.com/shopware/shopware/issues).

If you want to suggest features or how certain parts of Shopwell 6 work, we'd be happy to [hear from you](https://feedback.shopwell.cn/).

## Reporting security issues

Please review our [security policy](SECURITY.md).

### Extending Shopwell

There are already a lot of extensions available in the [Shopwell store](https://store.shopwell.cn/).

After setting up [Shopwell locally for development](https://developer.shopwell.cn/docs/guides/installation), you can start with our extension guides in the documentation.

The preferred way of extending Shopwell is through the [App System](https://developer.shopwell.cn/docs/guides/plugins/apps/app-base-guide).
If the feature you want to implement needs direct access to the Shopwell process and the database, you can also use the [plugin system](https://developer.shopwell.cn/docs/guides/plugins/plugins/plugin-base-guide).    
You can find an [overview and differentiation in the documentation](https://developer.shopwell.cn/docs/concepts/extensions).

### Privacy

Find out more about [privacy and data protection](https://www.shopwell.cn/en/privacy/website/).

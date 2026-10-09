<?php declare(strict_types=1);

use Danger\Config;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\AgenticCommercePluginHint;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\ComposerVersionConstraints;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\DangerConfigChanged;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\DeprecatedChangelogFormat;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\EntityRepositoryInFrontendLayer;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\IgnoredPhpstanErrorsInTouchedFiles;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\InlineRuleInDangerConfig;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\InvalidFileNameCharacters;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\LegacyTestsInSrc;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\MissingIntegrationTestInSplitSuite;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\MissingMigrationTests;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\MissingPackageAttributeInTests;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\MissingPullRequestDescription;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\MissingReleaseInfo;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\MissingUnitTests;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\PhpstanBaselineGrowth;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\RedisGroupUsage;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\RemovedTwigBlocks;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\RouteSnapshotExtension;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\ShopwellYamlConfigSchemaHint;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\SingleCoversClassInTests;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\SqlHeredocUsage;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\TraitUsageInNewUnitTests;

// danger runs on its own vendor-bin autoloader (vendor-bin/danger-php), which does not know the
// Shopwell namespaces — load the rule classes directly instead
foreach (glob(__DIR__ . '/src/Core/DevOps/StaticAnalyze/Danger/Rules/*.php') ?: [] as $ruleFile) {
    require_once $ruleFile;
}

return (new Config())
    ->useThreadOn(Config::REPORT_LEVEL_WARNING)
    ->useRule(new DangerConfigChanged())
    ->useRule(new InlineRuleInDangerConfig())
    ->useRule(new MissingPullRequestDescription())
    ->useRule(new DeprecatedChangelogFormat())
    ->useRule(new MissingReleaseInfo())
    ->useRule(new IgnoredPhpstanErrorsInTouchedFiles())
    ->useRule(new PhpstanBaselineGrowth())
    ->useRule(new EntityRepositoryInFrontendLayer())
    ->useRule(new ShopwellYamlConfigSchemaHint())
    ->useRule(new AgenticCommercePluginHint())
    ->useRule(new MissingMigrationTests())
    ->useRule(new MissingPackageAttributeInTests())
    ->useRule(new RedisGroupUsage())
    ->useRule(new SingleCoversClassInTests())
    ->useRule(new SqlHeredocUsage())
    ->useRule(new RemovedTwigBlocks())
    ->useRule(new InvalidFileNameCharacters())
    ->useRule(new LegacyTestsInSrc())
    ->useRule(new TraitUsageInNewUnitTests())
    ->useRule(new MissingUnitTests())
    ->useRule(new ComposerVersionConstraints())
    ->useRule(new MissingIntegrationTestInSplitSuite())
    ->useRule(new RouteSnapshotExtension())
;

import assert from 'node:assert/strict';
import { test, type TestContext as NodeTestContext } from 'node:test';
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import {
    ADMINISTRATION_DEPRECATION_BASELINE_PATH,
    FEATURE_REGISTRY_PATH,
    detectMajorTestArms,
    hasAcceptanceTestChanges,
    hasMajorJsMarkers,
    hasMajorMarkers,
    labelsForMajorTestArms,
    parseMajorFlags,
    shouldDetect,
} from './auto-label-major-tests.ts';


type TestContext = {
    eventName: string;
    repo: {
        owner: string;
        repo: string;
    };
    payload: {
        action: string;
        pull_request: {
            head: {
                repo: {
                    full_name: string;
                };
            };
            labels: Array<{ name: string }>;
        };
    };
};

const diffFor = (path: string, hunk: string): string => `diff --git a/${path} b/${path}
index 0000000..1111111 100644
--- a/${path}
+++ b/${path}
@@ -1,1 +1,1 @@
${hunk}`;

const REGISTRY = `shopwell:
    feature:
        flags:
            - name: v6.8.0.0
              default: false
              toggleable: false
            - name: WEBHOOKS_REWORK
              default: false
              major: v6.8.0.0
              toggleable: true
            - name: TELEMETRY_METRICS
              default: false
              toggleable: true
`;

test('parseMajorFlags returns only major flags', () => {
    assert.deepEqual(parseMajorFlags(REGISTRY), ['v6.8.0.0', 'WEBHOOKS_REWORK']);
});

test('quoted major flag in an added line matches', () => {
    const diff = diffFor('src/Core/Content/Cms/SalesChannel/CmsRoute.php', "+        if (Feature::isActive('v6.8.0.0')) {");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('quoted major flag in a removed line matches', () => {
    const diff = diffFor('src/Core/Framework/Webhook/Service/WebhookManager.php', "-        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => null);");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('deprecation tag annotation matches', () => {
    const diff = diffFor('src/Core/Framework/Feature.php', '+     * @deprecated tag:v6.9.0 - Will be removed');
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('registry file edits match regardless of line content', () => {
    const diff = diffFor(FEATURE_REGISTRY_PATH, '+            - name: NEW_FLAG');
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('major markers in Administration source or tests enable the major-js arm', () => {
    const sourceDiff = diffFor(
        'src/Administration/Resources/app/administration/src/app/component/example/index.ts',
        "+        return Shopwell.Feature.isActive('v6.8.0.0');",
    );
    const testDiff = diffFor(
        'src/Administration/Resources/app/administration/test/_setup/example.spec.ts',
        "+        it.deprecated('v6.8.0.0')('keeps the legacy path', () => {});",
    );

    assert.equal(hasMajorJsMarkers(sourceDiff, parseMajorFlags(REGISTRY)), true);
    assert.equal(hasMajorJsMarkers(testDiff, parseMajorFlags(REGISTRY)), true);
});

test('major markers in Storefront source and tests enable the major-js arm', () => {
    for (const path of [
        'src/Storefront/Resources/app/storefront/src/helper/feature.helper.js',
        'src/Storefront/Resources/app/storefront/test/helper/feature.helper.test.js',
    ]) {
        assert.equal(hasMajorJsMarkers(diffFor(path, "+ window.Feature.isActive('v6.8.0.0');"), parseMajorFlags(REGISTRY)), true);
    }
});

test('major markers outside Administration and Storefront source and tests do not enable the major-js arm', () => {
    const phpDiff = diffFor('src/Core/Framework/Feature.php', "+        Feature::isActive('v6.8.0.0');");
    const documentationDiff = diffFor(
        'src/Administration/Resources/app/administration/technical-docs/guide.md',
        '+ v6.8.0.0',
    );

    assert.equal(hasMajorJsMarkers(phpDiff, parseMajorFlags(REGISTRY)), false);
    assert.equal(hasMajorJsMarkers(documentationDiff, parseMajorFlags(REGISTRY)), false);
});

test('feature registry edits enable the major-js arm', () => {
    const diff = diffFor(FEATURE_REGISTRY_PATH, '+            - name: NEW_FLAG');

    assert.equal(hasMajorJsMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('Markdown inside JavaScript source and test directories does not enable the major-js arm', () => {
    for (const path of [
        'src/Administration/Resources/app/administration/src/README.md',
        'src/Administration/Resources/app/administration/test/README.md',
        'src/Storefront/Resources/app/storefront/src/README.md',
        'src/Storefront/Resources/app/storefront/test/README.md',
    ]) {
        assert.equal(hasMajorJsMarkers(diffFor(path, '+ v6.8.0.0'), parseMajorFlags(REGISTRY)), false, path);
    }
});

test('deprecation baseline edits enable the major-js arm', () => {
    const diff = diffFor(ADMINISTRATION_DEPRECATION_BASELINE_PATH, '+        "sw-select-base > sw-loader": ["major"]');

    assert.equal(hasMajorJsMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('labelsForMajorTestArms adds only missing relevant labels', () => {
    assert.deepEqual(labelsForMajorTestArms({ php: true, js: true, acceptance: false }), ['major-php', 'major-js']);
    assert.deepEqual(labelsForMajorTestArms({ php: true, js: true, acceptance: true }, [{ name: 'major-php' }]), ['major-js', 'major-acceptance']);
    assert.deepEqual(labelsForMajorTestArms({ php: false, js: true, acceptance: true }, [{ name: 'major-js' }, { name: 'major-acceptance' }]), []);
    assert.deepEqual(labelsForMajorTestArms({ php: false, js: false, acceptance: true }), ['major-acceptance']);
});

test('acceptance specs enable major ATS without feature-flag markers', () => {
    for (const path of ['tests/acceptance/tests/Example.spec.ts', 'tests/acceptance/tests/Checkout/Example.spec.ts']) {
        assert.equal(hasAcceptanceTestChanges(diffFor(path, '+await button.click();')), true);
        assert.equal(hasAcceptanceTestChanges(diffFor(path, '-await button.click();')), true);
    }
});

test('acceptance helpers, dependencies, docs and other specs do not enable major ATS', () => {
    for (const path of [
        'tests/acceptance/tasks/Example.spec.ts',
        'tests/acceptance/fixtures/Example.ts',
        'tests/acceptance/package.json',
        'tests/acceptance/tests/README.md',
        'tests/acceptance/tests/Example.ts',
        'src/Administration/Resources/app/administration/src/Example.spec.ts',
    ]) {
        assert.equal(hasAcceptanceTestChanges(diffFor(path, '+example')), false, path);
    }
    assert.equal(hasAcceptanceTestChanges(''), false);
});

test('non-major flag usage does not match', () => {
    const diff = diffFor('src/Core/Telemetry/Telemetry.php', "+        if (Feature::isActive('TELEMETRY_METRICS')) {");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('unrelated changes do not match', () => {
    const diff = diffFor('src/Core/Content/Cms/SalesChannel/CmsRoute.php', '+        $cmsPage = $this->cmsPageLoader->load($request, $criteria, $context)->getEntities()->first();');
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('empty diff does not match', () => {
    assert.equal(hasMajorMarkers('', parseMajorFlags(REGISTRY)), false);
});

test('flag usage in .github tooling does not match', () => {
    const diff = diffFor('.github/bin/js/auto-label-major-php.test.ts', "+    const diff = \"Feature::isActive('v6.8.0.0')\";");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('registry path mentioned inside a .github file does not match', () => {
    const diff = diffFor('.github/bin/js/auto-label-major-php.ts', `+export const FEATURE_REGISTRY_PATH = '${FEATURE_REGISTRY_PATH}';`);
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('mixed diff matches through the non-excluded file only', () => {
    const diff = diffFor('.github/workflows/php.yml', "+  # v6.8.0.0 gate") + '\n' + diffFor('src/Core/CustomCartProcessor.php', "+        if (Feature::isActive('v6.8.0.0')) {");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('added BC-change attribute usage matches through its version string', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "+    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('removed BC-change attribute usage matches through its version string', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "-    #[BecomesFinal(version: 'v6.8.0')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('version-free attributes do not match', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "+    #[Route(path: '/api/test', name: 'api.test')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('BC-change attribute in .github tooling does not match', () => {
    const diff = diffFor('.github/bin/js/auto-label-major-php.test.ts', "+    #[ReturnTypeNarrowing(version: 'v6.8.0')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('markdown never matches: release headings, upgrade notes and ADRs quote versions and flags', () => {
    const changelog = diffFor('CHANGELOG.md', '+## 6.6.10.21');
    const upgradeNotes = diffFor('UPGRADE-6.8.md', '+Tools return the MCP result format only behind the `v6.8.0.0` flag.');
    const adr = diffFor('adr/2026-09-24-mcp-tool-result-envelope.md', "+3. **Spec format only (6.8.0).** Behind the 'v6.8.0.0' flag.");
    const upperCase = diffFor('docs/README.MD', "+Feature::isActive('v6.8.0.0')");

    for (const diff of [changelog, upgradeNotes, adr, upperCase]) {
        assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
    }
});

test('markdown next to a code change does not hide the code change', () => {
    const diff = diffFor('adr/2026-09-24-mcp-tool-result-envelope.md', '+Behind the `v6.8.0.0` flag.') + '\n' + diffFor('src/Core/Framework/Mcp/McpToolResponse.php', "+        if (Feature::isActive('v6.8.0.0')) {");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('markdown inside Administration source does not enable the major-js arm', () => {
    const diff = diffFor('src/Administration/Resources/app/administration/src/app/component/example/README.md', "+Shopwell.Feature.isActive('v6.8.0.0')");
    assert.equal(hasMajorJsMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

const baseContext = (overrides: Partial<TestContext> = {}): TestContext => ({
    eventName: 'pull_request_target',
    repo: { owner: 'shopwell-shop', repo: 'shopwell' },
    payload: {
        action: 'opened',
        pull_request: {
            head: { repo: { full_name: 'shopwell-shop/shopwell' } },
            labels: [],
        },
    },
    ...overrides,
});

test('shouldDetect accepts an unlabeled same-repo PR being opened', () => {
    assert.equal(shouldDetect(baseContext()), true);
});

test('shouldDetect rejects events other than pull_request_target', () => {
    assert.equal(shouldDetect(baseContext({ eventName: 'issues' })), false);
    assert.equal(shouldDetect(baseContext({ eventName: 'pull_request' })), false);
});

test('shouldDetect rejects other PR actions', async () => {
    const context = baseContext();
    context.payload = { ...context.payload, action: 'labeled' };
    assert.equal(shouldDetect(context), false);
});

test('shouldDetect accepts fork heads', () => {
    const context = baseContext();
    context.payload.pull_request.head.repo.full_name = 'fork/shopwell';
    assert.equal(shouldDetect(context), true);
});

test('shouldDetect permits detection until all three per-arm labels are present', () => {
    const phpOnly = baseContext();
    phpOnly.payload.pull_request.labels = [{ name: 'major-php' }];
    assert.equal(shouldDetect(phpOnly), true);

    const bothArms = baseContext();
    bothArms.payload.pull_request.labels = [{ name: 'major-php' }, { name: 'major-js' }];
    assert.equal(shouldDetect(bothArms), true);

    bothArms.payload.pull_request.labels.push({ name: 'major-acceptance' });
    assert.equal(shouldDetect(bothArms), false);

    const umbrella = baseContext();
    umbrella.payload.pull_request.labels = [{ name: 'major-tests' }];
    assert.equal(shouldDetect(umbrella), false);
});

test('detection selects only the acceptance arm for a spec change after PHP and JS were labeled', async () => {
    const context = baseContext();
    context.payload.pull_request.labels = [{ name: 'major-php' }, { name: 'major-js' }];
    const arms = await detectMajorTestArms({
        context: {
            ...context,
            payload: {
                ...context.payload,
                pull_request: {
                    ...context.payload.pull_request,
                    number: 123,
                    base: { sha: 'base-sha' },
                    head: { ...context.payload.pull_request.head, sha: 'head-sha' },
                },
            },
        },
        core: { info() {} },
        github: {
            rest: {
                repos: {
                    async getContent() { return { data: REGISTRY }; },
                    async compareCommitsWithBasehead() { throw new Error('Unexpected fallback'); },
                },
                pulls: { async get() { return { data: diffFor('tests/acceptance/tests/Example.spec.ts', '+await button.click();') }; } },
                issues: { async addLabels() {} },
            },
        },
    });
    assert.deepEqual(arms, { php: false, js: false, acceptance: true });
    assert.deepEqual(labelsForMajorTestArms(arms, context.payload.pull_request.labels), ['major-acceptance']);
});


const tooLargeDiff = () => Object.assign(new Error('diff too large'), {
    status: 406,
    response: { data: { errors: [{ field: 'diff', code: 'too_large' }] } },
});

function gitFixture(t: NodeTestContext, files: Record<string, string>, baseFiles: Record<string, string> = {}) {
    const root = mkdtempSync(join(tmpdir(), 'major-label-test-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    const origin = join(root, 'origin');
    const cwd = join(root, 'checkout');
    const git = (...args: string[]) => execFileSync('git', args, { cwd: origin, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
    mkdirSync(origin);
    git('init', '--initial-branch=main');
    git('config', 'user.email', 'test@example.com');
    git('config', 'user.name', 'Test');
    const writeFiles = (entries: Record<string, string>) => {
        for (const [path, text] of Object.entries(entries)) {
            mkdirSync(dirname(join(origin, path)), { recursive: true });
            writeFileSync(join(origin, path), text);
        }
    };
    writeFiles({ 'README.md': 'initial\n', ...baseFiles });
    git('add', '.');
    git('commit', '-m', 'initial');
    const mergeBase = git('rev-parse', 'HEAD');
    git('checkout', '-b', 'change');
    writeFiles(files);
    git('add', '.');
    git('commit', '-m', 'change');
    const head = git('rev-parse', 'HEAD');
    git('checkout', 'main');
    writeFiles({ 'src/Core/BaseOnly.php': "<?php // v6.8.0.0\n" });
    git('add', '.');
    git('commit', '-m', 'advance base');
    const base = git('rev-parse', 'HEAD');
    execFileSync('git', ['clone', '--depth=1', `file://${origin}`, cwd], { stdio: 'pipe' });

    return { cwd, head, base, mergeBase };
}

function largeDiffToolkit(fixture: { head: string; base: string; mergeBase: string }, error: unknown = tooLargeDiff()) {
    return {
        context: {
            ...baseContext(),
            payload: {
                action: 'opened',
                pull_request: {
                    number: 123,
                    base: { sha: fixture.base },
                    head: { sha: fixture.head, repo: { full_name: 'fork/shopwell' } },
                    labels: [],
                },
            },
        },
        core: { info() {} },
        github: {
            rest: {
                repos: {
                    async getContent() { return { data: REGISTRY }; },
                    async compareCommitsWithBasehead(options: { basehead: string }) {
                        assert.equal(options.basehead, `${fixture.base}...${fixture.head}`);
                        return { data: { merge_base_commit: { sha: fixture.mergeBase } } };
                    },
                },
                pulls: { async get(): Promise<{ data: unknown }> { throw error; } },
                issues: { async addLabels() {} },
            },
        },
    };
}

test('406 too_large uses the full exact merge-base diff without checking out fork code', async (t) => {
    const path = 'src/Administration/Resources/app/administration/src/example.ts';
    const fixture = gitFixture(t, {
        [path]: "Feature.isActive('WEBHOOKS_REWORK');\n",
        'tests/acceptance/tests/Example.spec.ts': 'await button.click();\n',
        'untrusted.sh': 'exit 99\n',
    });
    const arms = await detectMajorTestArms(largeDiffToolkit(fixture), { cwd: fixture.cwd });

    assert.deepEqual(arms, { php: true, js: true, acceptance: true });
    assert.equal(execFileSync('git', ['rev-parse', 'HEAD'], { cwd: fixture.cwd, encoding: 'utf8' }).trim(), fixture.base);
    assert.equal(execFileSync('git', ['status', '--porcelain'], { cwd: fixture.cwd, encoding: 'utf8' }), '');
});

test('406 fallback examines markers and acceptance specs beyond 3000 changed files', async (t) => {
    const files = Object.fromEntries(Array.from({ length: 3001 }, (_, index) => [`aaa/file-${index}.txt`, 'plain\n']));
    files['src/Administration/Resources/app/administration/src/z-last.ts'] = "Feature.isActive('WEBHOOKS_REWORK');\n";
    files['tests/acceptance/tests/ZLast.spec.ts'] = 'await button.click();\n';
    const fixture = gitFixture(t, files);

    assert.deepEqual(await detectMajorTestArms(largeDiffToolkit(fixture), { cwd: fixture.cwd }), {
        php: true, js: true, acceptance: true,
    });
});

test('406 fallback preserves docs exclusions and ignores base-only markers', async (t) => {
    const fixture = gitFixture(t, {
        'docs/README.md': 'v6.8.0.0\n',
        '.github/example.ts': "const marker = 'WEBHOOKS_REWORK';\n",
        'tests/acceptance/fixtures/Example.ts': 'fixture\n',
    });

    assert.deepEqual(await detectMajorTestArms(largeDiffToolkit(fixture), { cwd: fixture.cwd }), {
        php: false, js: false, acceptance: false,
    });
});

test('406 fallback sees removed markers in a file too large for an API patch', async (t) => {
    const path = 'src/Core/Example.php';
    const fixture = gitFixture(t, { [path]: '<?php\n' }, {
        [path]: "<?php\nFeature::isActive('WEBHOOKS_REWORK');\n" + `${'plain line '.repeat(10)}\n`.repeat(21000),
    });

    assert.deepEqual(await detectMajorTestArms(largeDiffToolkit(fixture), { cwd: fixture.cwd }), {
        php: true, js: false, acceptance: false,
    });
});

test('406 fallback fails closed when exact commits cannot be fetched', async (t) => {
    const fixture = gitFixture(t, { 'src/Core/Example.php': '<?php\n' });
    execFileSync('git', ['remote', 'set-url', 'origin', join(fixture.cwd, 'missing')], { cwd: fixture.cwd });

    await assert.rejects(detectMajorTestArms(largeDiffToolkit(fixture), { cwd: fixture.cwd }), /git|fetch/);
});

test('406 fallback rejects invalid merge-base SHAs before invoking Git', async (t) => {
    const fixture = gitFixture(t, { 'src/Core/Example.php': '<?php\n' });
    fixture.mergeBase = '--help';

    await assert.rejects(detectMajorTestArms(largeDiffToolkit(fixture), { cwd: fixture.cwd }), /SHA/);
});

test('ordinary API failures are propagated instead of being treated as oversized diffs', async () => {
    const error = Object.assign(new Error('unavailable'), { status: 503 });
    const fixture = { base: 'a'.repeat(40), head: 'b'.repeat(40), mergeBase: 'c'.repeat(40) };

    await assert.rejects(detectMajorTestArms(largeDiffToolkit(fixture, error)), error);
});


test('a 406 unrelated to diff size is propagated', async () => {
    const error = Object.assign(new Error('unacceptable'), { status: 406 });
    const fixture = { base: 'a'.repeat(40), head: 'b'.repeat(40), mergeBase: 'c'.repeat(40) };

    await assert.rejects(detectMajorTestArms(largeDiffToolkit(fixture, error)), error);
});


test('406 fallback retains active conditional checkout configuration', async (t) => {
    const fixture = gitFixture(t, { 'tests/acceptance/tests/Example.spec.ts': 'await button.click();\n' });
    const git = (...args: string[]) => execFileSync('git', args, { cwd: fixture.cwd, encoding: 'utf8' }).trim();
    const origin = git('remote', 'get-url', 'origin');
    const credentials = join(fixture.cwd, '.git', 'checkout-auth.config');
    git('config', '--file', credentials, 'remote.origin.url', origin);
    git('config', '--unset', 'remote.origin.url');
    git('config', `includeIf.gitdir:${git('rev-parse', '--absolute-git-dir')}.path`, credentials);
    assert.equal(git('remote', 'get-url', 'origin'), origin);

    assert.deepEqual(await detectMajorTestArms(largeDiffToolkit(fixture), { cwd: fixture.cwd }), {
        php: false, js: false, acceptance: true,
    });
});

import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    DEFINITION_FILE,
    LEGAL_FILES,
    PACKAGE_NAME,
    REPOSITORY_URL,
    definitionProblems,
    entitySchemaPackageVersion,
    manifestProblems,
    releaseTagFromEnvironment,
} from './entity-schema-package.ts';

const manifestFor = (version = '7.15.0'): Record<string, unknown> => ({
    name: PACKAGE_NAME,
    version,
    license: 'Apache-2.0',
    types: DEFINITION_FILE,
    repository: { type: 'git', url: REPOSITORY_URL },
    publishConfig: { access: 'public' },
    files: [DEFINITION_FILE, ...LEGAL_FILES],
});

test('derives the npm version from a release tag the same way the generator does', () => {
    assert.equal(entitySchemaPackageVersion('v6.7.15.0'), '7.15.0');
});

test('derives the version the first upstream release was published under', () => {
    assert.equal(entitySchemaPackageVersion('v6.6.9.0'), '6.9.0');
});

test('keeps a release-candidate suffix that upstream also publishes', () => {
    assert.equal(entitySchemaPackageVersion('v6.5.0.0-rc2'), '5.0.0-rc2');
});

test('tolerates surrounding whitespace from a ref name', () => {
    assert.equal(entitySchemaPackageVersion('  v6.7.15.0\n'), '7.15.0');
});

for (const tag of ['trunk', '6.7.15.0', 'v7.0.0.0', 'v6.7.15', 'v6.7.15.0.1', '']) {
    test(`refuses to derive a version from ${JSON.stringify(tag)}`, () => {
        assert.throws(() => entitySchemaPackageVersion(tag), /not a release tag/);
    });
}

test('prefers the tag the upstream generator reads over the GitHub ref name', () => {
    assert.equal(
        releaseTagFromEnvironment({ CI_COMMIT_TAG: 'v6.7.15.0', GITHUB_REF_NAME: 'trunk' }),
        'v6.7.15.0',
    );
});

test('falls back to the GitHub ref name when the generator variable is absent', () => {
    assert.equal(releaseTagFromEnvironment({ GITHUB_REF_NAME: 'v6.7.15.0' }), 'v6.7.15.0');
});

test('fails rather than guessing when no tag is available', () => {
    assert.throws(() => releaseTagFromEnvironment({}), /cannot tell which release is being published/);
});

test('accepts the manifest the generator is expected to write', () => {
    assert.deepEqual(manifestProblems(manifestFor(), '7.15.0'), []);
});

test('reports a version that does not match the release tag', () => {
    assert.deepEqual(manifestProblems(manifestFor('5.8.6'), '7.15.0'), [
        'version is "5.8.6", expected "7.15.0"',
    ]);
});

test('reports a repository npm trusted publishing would reject', () => {
    const problems = manifestProblems({ ...manifestFor(), repository: { url: 'git+https://github.com/shopware/shopware.git' } }, '7.15.0');

    assert.deepEqual(problems, [`repository.url is "git+https://github.com/shopware/shopware.git", expected ${JSON.stringify(REPOSITORY_URL)}`]);
});

test('reports a scoped package left at the restricted default', () => {
    assert.deepEqual(manifestProblems({ ...manifestFor(), publishConfig: undefined }, '7.15.0'), [
        'publishConfig.access is undefined, expected "public"',
    ]);
});

test('reports a package name that would publish to the wrong scope', () => {
    assert.deepEqual(manifestProblems({ ...manifestFor(), name: '@shopware-ag/entity-schema-types' }, '7.15.0'), [
        `name is "@shopware-ag/entity-schema-types", expected ${JSON.stringify(PACKAGE_NAME)}`,
    ]);
});

test('reports the upstream license that no longer matches this repository', () => {
    assert.deepEqual(manifestProblems({ ...manifestFor(), license: 'MIT' }, '7.15.0'), [
        'license is "MIT", expected "Apache-2.0"',
    ]);
});

test('reports legal files npm would then not pack', () => {
    const problems = manifestProblems({ ...manifestFor(), files: [DEFINITION_FILE] }, '7.15.0');

    assert.deepEqual(problems, [
        'files does not whitelist "LICENSE"',
        'files does not whitelist "NOTICE"',
    ]);
});

test('reports a files field that is not a list at all', () => {
    assert.deepEqual(manifestProblems({ ...manifestFor(), files: DEFINITION_FILE }, '7.15.0'), [
        `files is "${DEFINITION_FILE}", expected an array whitelisting the definition and legal files`,
    ]);
});

test('accepts a definition with content', () => {
    assert.deepEqual(definitionProblems('export type Entity = { id: string };\n'), []);
});

test('reports the empty-schema stub a missing schema dump leaves behind', () => {
    assert.deepEqual(definitionProblems('\n'), [`${DEFINITION_FILE} is empty`]);
});

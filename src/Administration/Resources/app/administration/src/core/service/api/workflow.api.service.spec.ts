/** @sw-package framework */
import MockAdapter from 'axios-mock-adapter';
import WorkflowApiService from './workflow.api.service';
import createLoginService from '../login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import { createWorkflowDraft } from 'src/core/workflow/workflow-document';

function createService() {
    const context = Shopwell.Context.api;
    const client = createHTTPClient(context);
    return { service: new WorkflowApiService(client, createLoginService(client, context)), mock: new MockAdapter(client) };
}

describe('workflowService', () => {
    it('lists publishers, publishes an exact revision, and reads immutable releases', async () => {
        const { service, mock } = createService();
        const publishers = [{ name: 'example:approval', labelKey: 'example.publisher', canPublish: true }];
        const release = {
            id: 'release',
            draftId: 'draft',
            revision: 3,
            name: 'Review',
            publisher: 'example:approval',
            createdAt: '2026-11-13T00:00:00Z',
        };
        mock.onGet('/_action/workflow/publishers').reply(200, { data: publishers });
        await expect(service.getPublishers()).resolves.toEqual(publishers);
        mock.onPost('/_action/workflow/draft/draft/publish', { expectedRevision: 3, publisher: 'example:approval' }).reply(
            201,
            release,
        );
        await expect(service.publishDraft('draft', 3, 'example:approval')).resolves.toEqual(release);
        mock.onGet('/_action/workflow/releases', { params: { draftId: 'draft', limit: 25, offset: 0 } }).reply(200, {
            data: [release],
            total: 1,
        });
        await expect(service.getReleases({ draftId: 'draft' })).resolves.toEqual({ data: [release], total: 1 });
        const document = createWorkflowDraft();
        mock.onGet('/_action/workflow/release/release').reply(200, { ...release, document });
        await expect(service.getRelease('release')).resolves.toEqual({ ...release, document });
    });

    it('propagates publication validation and revision conflicts', async () => {
        const { service, mock } = createService();
        mock.onPost('/_action/workflow/draft/draft/publish').replyOnce(422, {
            errors: [{ code: 'FRAMEWORK__WORKFLOW_PUBLICATION_REJECTED', detail: 'Unsupported' }],
        });
        mock.onPost('/_action/workflow/draft/draft/publish').reply(409, {
            errors: [{ code: 'FRAMEWORK__WORKFLOW_DRAFT_CONFLICT', detail: 'Conflict' }],
        });
        await expect(service.publishDraft('draft', 1, 'example:approval')).rejects.toThrow();
        await expect(service.publishDraft('draft', 1, 'example:approval')).rejects.toThrow();
    });

    it('persists and deletes drafts with explicit revision tokens', async () => {
        const { service, mock } = createService();
        const document = createWorkflowDraft();
        const draft = { id: 'draft', name: 'Review', revision: 1, document };
        mock.onPost('/_action/workflow/draft', { id: 'draft', name: 'Review', document }).reply(201, draft);
        await expect(service.createDraft('draft', 'Review', document)).resolves.toEqual(draft);
        mock.onGet('/_action/workflow/draft/draft').reply(200, draft);
        await expect(service.getDraft('draft')).resolves.toEqual(draft);
        mock.onPut('/_action/workflow/draft/draft', { name: 'Renamed', document, expectedRevision: 1 }).reply(409, {
            errors: [{ code: 'FRAMEWORK__WORKFLOW_DRAFT_CONFLICT', detail: 'Conflict' }],
        });
        await expect(service.updateDraft('draft', 'Renamed', document, 1)).rejects.toThrow();
        mock.onDelete('/_action/workflow/draft/draft', { data: { expectedRevision: 1 } }).reply(204);
        await expect(service.deleteDraft('draft', 1)).resolves.toBeUndefined();
    });

    it('loads the registered types', async () => {
        const { service, mock } = createService();
        const types = [{ name: 'example:node', version: 1, kind: 'activity', inputPorts: ['in'], outputPorts: ['out'] }];
        mock.onGet('/_info/workflow-node-types.json').reply(200, { types });
        await expect(service.getNodeTypes()).resolves.toEqual(types);
    });

    it('posts the document without canvas state and returns diagnostics', async () => {
        const { service, mock } = createService();
        const document = createWorkflowDraft();
        const violations = [{ code: 'unknown-node-type', path: '/nodes/0', nodeId: 'start' }];
        mock.onPost('/_action/workflow/diagnose', document).reply(200, { violations });
        await expect(service.diagnose(document)).resolves.toEqual(violations);
    });

    it('propagates API failures for the page to display', async () => {
        const { service, mock } = createService();
        mock.onGet('/_info/workflow-node-types.json').reply(403, {
            errors: [{ code: 'FRAMEWORK__MISSING_PRIVILEGE', detail: 'Forbidden' }],
        });
        mock.onPost('/_action/workflow/diagnose').reply(400, {
            errors: [{ code: 'FRAMEWORK__WORKFLOW_INVALID_DOCUMENT', detail: 'Invalid' }],
        });
        await expect(service.getNodeTypes()).rejects.toThrow();
        await expect(service.diagnose(createWorkflowDraft())).rejects.toThrow();
    });
});

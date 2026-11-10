/**
 * @sw-package discovery
 */

import PrivilegesService from 'src/app/service/privileges.service';

Shopwell.Application.addServiceProvider('privileges', () => new PrivilegesService());

describe('Experience Studio Blog assignment permissions', () => {
    beforeEach(() => {
        jest.resetModules();
        jest.requireActual('./index');
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('lets viewers load assignments and select a Blog preview without granting writes', () => {
        const viewer = Shopwell.Service('privileges').getPrivilegeRole('experience_studio.viewer');
        expect(viewer).toBeDefined();

        expect(viewer?.privileges).toEqual(
            expect.arrayContaining(['blog_content_layout:read', 'blog:read', 'sales_channel:read']),
        );
        expect(viewer?.privileges).not.toContain('blog_content_layout:create');
        expect(viewer?.privileges).not.toContain('blog_content_layout:update');
        expect(viewer?.privileges).not.toContain('blog_content_layout:delete');
        expect(viewer?.privileges).not.toContain('blog:update');
    });

    it('lets editors maintain assignments while inheriting read permissions', () => {
        const editor = Shopwell.Service('privileges').getPrivilegeRole('experience_studio.editor');
        expect(editor).toBeDefined();

        expect(editor?.privileges).toEqual(
            expect.arrayContaining([
                'blog_content_layout:create',
                'blog_content_layout:update',
                'blog_content_layout:delete',
            ]),
        );
        expect(editor?.dependencies).toContain('experience_studio.viewer');
        expect(editor?.privileges).not.toContain('blog:update');
    });
});

type WorkspaceCreator = Pick<Entity<'user'>, 'name' | 'username'>;

/** @private @sw-package discovery */
export function getWorkspaceCreatorName(workspace: { createdBy?: WorkspaceCreator | null }): string {
    const creator = workspace.createdBy;
    return creator?.name?.trim() || creator?.username?.trim() || '';
}

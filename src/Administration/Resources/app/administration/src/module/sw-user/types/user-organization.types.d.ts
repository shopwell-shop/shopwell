/** @sw-package fundamentals@framework */

/** @private */
export type DepartmentAssignment = {
    id: string;
    organizationUnitId: string;
    isPrimary: boolean;
    isLeader: boolean;
    leaderPosition: number;
    supervisorMembershipId: string | null;
    positionIds: string[];
};

/** @private */
export type OrganizationAssignments = {
    departments: DepartmentAssignment[];
    primaryPosition: string | null;
};

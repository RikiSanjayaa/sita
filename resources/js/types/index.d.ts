import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export type AppRole = 'mahasiswa' | 'dosen' | 'admin' | 'penguji';

export interface Auth {
    user: User;
    activeRole: AppRole | null;
    availableRoles: AppRole[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface RoleScopedNavItem extends NavItem {
    roles: AppRole[];
}

export interface MentorshipAssignmentSummary {
    id: number;
    studentUserId: number;
    lecturerUserId: number;
    advisorType: 'primary' | 'secondary';
    status: 'active' | 'ended';
}

export interface SystemActivityEvent {
    id: string;
    type: 'assignment' | 'jadwal' | 'dokumen' | 'chat-escalation' | 'status';
    actor: string;
    description: string;
    timestamp: string;
}

export interface SharedData {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    roles?: AppRole[];
    last_active_role?: AppRole | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

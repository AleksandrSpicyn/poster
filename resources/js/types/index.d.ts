import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from '@lucide/vue';
import type { Config } from 'ziggy-js';
import type { Balance } from './billing';

export interface Auth {
    user: User;
    can_view_logs: boolean;
    can_view_queues: boolean;
    can_manage_series: boolean;
    can_manage_channels: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type Appearance = 'light' | 'dark' | 'system';

export interface AppearanceValues {
    LIGHT: Appearance;
    DARK: Appearance;
    SYSTEM: Appearance;
}

/** Общий контракт HandleInertiaRequests; props конкретных страниц добавляются отдельно. */
export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    balance: Balance | null;
    appearanceValues: AppearanceValues;
    realtime: { enabled: boolean; key: string | null };
    contentScheduleEnabled: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type ChannelType = 'youtube' | 'instagram' | 'tiktok' | 'vk';

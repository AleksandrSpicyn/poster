import type { ChannelType } from '@/types';

export interface ManagedChannel {
    id: number;
    name: string;
    type: ChannelType;
    type_label: string;
    is_active: boolean;
    connected: boolean;
    account_name: string | null;
    expires_at: string | null;
    client_id: string | null;
    has_client_secret: boolean;
    oauth_configured: boolean;
    can_delete: boolean;
    posts_count?: number;
    projects?: { id: number; name: string }[];
}

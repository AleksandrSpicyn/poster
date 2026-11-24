import type { ProjectSummary } from '@/types/projects';

export interface TiktokPublicationProps {
    channelId: number;
    account: string;
    accountId: string;
    creator: { nickname: string; privacy: string[]; comment: boolean; duet: boolean; stitch: boolean; duration: number } | null;
    inbox: boolean;
    directUnavailable: boolean;
    videos: {
        data: {
            id: number;
            label: string;
            url: string;
            project: Pick<ProjectSummary, 'id' | 'name' | 'cover'> | null;
            assembly_number: number | null;
            receipt: {
                status: string;
                current_recipient: boolean;
                submitted_at: string | null;
                published_at: string | null;
                url: string | null;
            } | null;
        }[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
}

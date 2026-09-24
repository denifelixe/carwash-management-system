import demoMember from '@/routes/demo/member';
import { dashboard, profile, rewards, services, stamps } from '@/routes/member';

export type MemberPortalMode = 'demo' | 'live';

export type MemberPortalUrls = {
    dashboard: string;
    stamps: string;
    services: string;
    rewards: string;
    profile: string;
};

/**
 * The portal pages are shared by the demo and the live member domain, so every
 * link between them is resolved from the page's `mode` prop.
 */
export function memberPortalUrls(mode: MemberPortalMode): MemberPortalUrls {
    if (mode === 'live') {
        return {
            dashboard: dashboard.url(),
            stamps: stamps.url(),
            services: services.url(),
            rewards: rewards.url(),
            profile: profile.url(),
        };
    }

    return {
        dashboard: demoMember.dashboard.url(),
        stamps: demoMember.stamps.url(),
        services: demoMember.services.url(),
        rewards: demoMember.rewards.url(),
        profile: demoMember.profile.url(),
    };
}

/**
 * Wayfinder URLs carry the domain (`//member.example.test/dashboard`), while
 * Inertia's page URL is a path, so tabs are compared on the path alone.
 */
export function urlPath(url: string): string {
    return new URL(url, 'http://portal.local').pathname;
}

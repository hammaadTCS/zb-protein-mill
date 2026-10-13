import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { MobileBottomBar } from '@/components/app/MobileBottomBar';
import { StagingBanner } from '@/components/app/StagingBanner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <StagingBanner />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
                <MobileBottomBar />
            </AppContent>
        </AppShell>
    );
}

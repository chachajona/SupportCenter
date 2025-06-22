import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

interface LoadingSkeletonProps {
    /**
     * Type of content being loaded
     */
    type?: 'card' | 'table' | 'list' | 'form' | 'dashboard';

    /**
     * Number of items to show
     */
    count?: number;

    /**
     * Additional CSS classes
     */
    className?: string;

    /**
     * Show text underneath skeleton
     */
    label?: string;
}

/**
 * Loading skeleton component for RBAC content
 * Provides accessible loading states for different types of RBAC interfaces
 */
export function LoadingSkeleton({ type = 'card', count = 1, className, label }: LoadingSkeletonProps) {
    const renderCardSkeleton = () => (
        <div className="space-y-3 rounded-lg border p-4">
            <div className="flex items-center justify-between">
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-4 w-16" />
            </div>
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-3/4" />
            <div className="flex items-center gap-2 pt-2">
                <Skeleton className="h-8 w-16" />
                <Skeleton className="h-8 w-20" />
            </div>
        </div>
    );

    const renderTableSkeleton = () => (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <Skeleton className="h-8 w-48" />
                <Skeleton className="h-8 w-24" />
            </div>
            <div className="rounded-lg border">
                <div className="border-b p-4">
                    <div className="flex items-center gap-4">
                        <Skeleton className="h-4 w-32" />
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-4 w-20" />
                        <Skeleton className="h-4 w-16" />
                    </div>
                </div>
                {Array.from({ length: count }).map((_, i) => (
                    <div key={i} className="border-b p-4 last:border-b-0">
                        <div className="flex items-center gap-4">
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-4 w-24" />
                            <Skeleton className="h-4 w-20" />
                            <Skeleton className="h-4 w-16" />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );

    const renderListSkeleton = () => (
        <div className="space-y-3">
            {Array.from({ length: count }).map((_, i) => (
                <div key={i} className="flex items-center justify-between rounded-lg border p-4">
                    <div className="flex items-center gap-3">
                        <Skeleton className="h-8 w-8 rounded-full" />
                        <div className="space-y-1">
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-3 w-24" />
                        </div>
                    </div>
                    <Skeleton className="h-8 w-16" />
                </div>
            ))}
        </div>
    );

    const renderFormSkeleton = () => (
        <div className="space-y-6 rounded-lg border p-6">
            <div className="space-y-2">
                <Skeleton className="h-5 w-24" />
                <Skeleton className="h-10 w-full" />
            </div>
            <div className="space-y-2">
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-20 w-full" />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Skeleton className="h-5 w-20" />
                    <Skeleton className="h-10 w-full" />
                </div>
                <div className="space-y-2">
                    <Skeleton className="h-5 w-16" />
                    <Skeleton className="h-10 w-full" />
                </div>
            </div>
            <div className="flex justify-end gap-2">
                <Skeleton className="h-10 w-20" />
                <Skeleton className="h-10 w-24" />
            </div>
        </div>
    );

    const renderDashboardSkeleton = () => (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className="rounded-lg border p-4">
                        <div className="flex items-center justify-between">
                            <Skeleton className="h-4 w-24" />
                            <Skeleton className="h-4 w-4 rounded" />
                        </div>
                        <Skeleton className="mt-2 h-8 w-16" />
                        <Skeleton className="mt-1 h-3 w-20" />
                    </div>
                ))}
            </div>
            <div className="grid gap-6 md:grid-cols-2">
                <div className="rounded-lg border p-4">
                    <Skeleton className="h-5 w-32" />
                    <div className="mt-4 space-y-3">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <div key={i} className="flex items-center justify-between">
                                <Skeleton className="h-4 w-40" />
                                <Skeleton className="h-4 w-16" />
                            </div>
                        ))}
                    </div>
                </div>
                <div className="rounded-lg border p-4">
                    <Skeleton className="h-5 w-28" />
                    <Skeleton className="mt-4 h-40 w-full" />
                </div>
            </div>
        </div>
    );

    const renderContent = () => {
        switch (type) {
            case 'table':
                return renderTableSkeleton();
            case 'list':
                return renderListSkeleton();
            case 'form':
                return renderFormSkeleton();
            case 'dashboard':
                return renderDashboardSkeleton();
            case 'card':
            default:
                return (
                    <div className="space-y-4">
                        {Array.from({ length: count }).map((_, i) => (
                            <div key={i}>{renderCardSkeleton()}</div>
                        ))}
                    </div>
                );
        }
    };

    return (
        <div className={cn('animate-pulse', className)} role="status" aria-label={label || 'Loading content'} aria-live="polite">
            {renderContent()}
            {label && <p className="sr-only">{label}</p>}
        </div>
    );
}

/**
 * Specialized loading skeletons for common RBAC scenarios
 */
export const RoleCardsSkeleton = () => (
    <LoadingSkeleton type="card" count={6} label="Loading roles" className="grid gap-6 md:grid-cols-2 lg:grid-cols-3" />
);

export const PermissionTableSkeleton = () => <LoadingSkeleton type="table" count={10} label="Loading permissions" />;

export const UserListSkeleton = () => <LoadingSkeleton type="list" count={8} label="Loading users" />;

export const AuditLogSkeleton = () => <LoadingSkeleton type="list" count={15} label="Loading audit logs" />;

export const RBACDashboardSkeleton = () => <LoadingSkeleton type="dashboard" label="Loading RBAC dashboard" />;

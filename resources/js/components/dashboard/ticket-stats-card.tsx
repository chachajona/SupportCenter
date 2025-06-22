import { GridPattern } from '@/components/pattern/grid-pattern';
import { cn } from '@/lib/utils';
import { LucideIcon } from 'lucide-react';
import { useState } from 'react';

interface TicketStatsCardProps {
    title: string;
    count: number;
    icon: LucideIcon;
    trend?: {
        value: number;
        isPositive: boolean;
    };
    color: 'blue' | 'orange' | 'green' | 'purple';
    className?: string;
}

const colorVariants = {
    blue: {
        bg: 'bg-blue-50 dark:bg-blue-950/20',
        border: 'border-blue-200 dark:border-blue-800/50',
        icon: 'text-blue-600 dark:text-blue-400',
        iconBg: 'bg-blue-100 dark:bg-blue-900/30',
        count: 'text-blue-900 dark:text-blue-100',
        trend: 'text-blue-600 dark:text-blue-400',
        patternFill: 'fill-blue-900/[0.02] dark:fill-blue-100/1',
        patternStroke: 'stroke-blue-900/5 dark:stroke-blue-100/2.5',
        gradient: 'from-blue-100 to-blue-200 dark:from-blue-900/20 dark:to-blue-800/30',
        hoverGradient: 'from-blue-200/80 to-blue-300/60 dark:from-blue-800/40 dark:to-blue-700/60',
        patternHoverFill: 'fill-blue-900/50 dark:fill-blue-100/2.5',
        patternHoverStroke: 'stroke-blue-900/70 dark:stroke-blue-100/10',
    },
    orange: {
        bg: 'bg-orange-50 dark:bg-orange-950/20',
        border: 'border-orange-200 dark:border-orange-800/50',
        icon: 'text-orange-600 dark:text-orange-400',
        iconBg: 'bg-orange-100 dark:bg-orange-900/30',
        count: 'text-orange-900 dark:text-orange-100',
        trend: 'text-orange-600 dark:text-orange-400',
        patternFill: 'fill-orange-900/[0.02] dark:fill-orange-100/1',
        patternStroke: 'stroke-orange-900/5 dark:stroke-orange-100/2.5',
        gradient: 'from-orange-100 to-orange-200 dark:from-orange-900/20 dark:to-orange-800/30',
        hoverGradient: 'from-orange-200/80 to-orange-300/60 dark:from-orange-800/40 dark:to-orange-700/60',
        patternHoverFill: 'fill-orange-900/50 dark:fill-orange-100/2.5',
        patternHoverStroke: 'stroke-orange-900/70 dark:stroke-orange-100/10',
    },
    green: {
        bg: 'bg-green-50 dark:bg-green-950/20',
        border: 'border-green-200 dark:border-green-800/50',
        icon: 'text-green-600 dark:text-green-400',
        iconBg: 'bg-green-100 dark:bg-green-900/30',
        count: 'text-green-900 dark:text-green-100',
        trend: 'text-green-600 dark:text-green-400',
        patternFill: 'fill-green-900/[0.02] dark:fill-green-100/1',
        patternStroke: 'stroke-green-900/5 dark:stroke-green-100/2.5',
        gradient: 'from-green-100 to-green-200 dark:from-green-900/20 dark:to-green-800/30',
        hoverGradient: 'from-green-200/80 to-green-300/60 dark:from-green-800/40 dark:to-green-700/60',
        patternHoverFill: 'fill-green-900/50 dark:fill-green-100/2.5',
        patternHoverStroke: 'stroke-green-900/70 dark:stroke-green-100/10',
    },
    purple: {
        bg: 'bg-purple-50 dark:bg-purple-950/20',
        border: 'border-purple-200 dark:border-purple-800/50',
        icon: 'text-purple-600 dark:text-purple-400',
        iconBg: 'bg-purple-100 dark:bg-purple-900/30',
        count: 'text-purple-900 dark:text-purple-100',
        trend: 'text-purple-600 dark:text-purple-400',
        patternFill: 'fill-purple-900/[0.02] dark:fill-purple-100/1',
        patternStroke: 'stroke-purple-900/5 dark:stroke-purple-100/2.5',
        gradient: 'from-purple-100 to-purple-200 dark:from-purple-900/20 dark:to-purple-800/30',
        hoverGradient: 'from-purple-200/80 to-purple-300/60 dark:from-purple-800/40 dark:to-purple-700/60',
        patternHoverFill: 'fill-purple-900/50 dark:fill-purple-100/2.5',
        patternHoverStroke: 'stroke-purple-900/70 dark:stroke-purple-100/10',
    },
};

export function TicketStatsCard({ title, count, icon: Icon, trend, color, className }: TicketStatsCardProps) {
    const colors = colorVariants[color];
    const [mousePosition, setMousePosition] = useState({ x: 0, y: 0 });
    const [isHovered, setIsHovered] = useState(false);

    const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
        const rect = e.currentTarget.getBoundingClientRect();
        setMousePosition({
            x: e.clientX - rect.left,
            y: e.clientY - rect.top,
        });
    };

    const handleMouseEnter = () => setIsHovered(true);
    const handleMouseLeave = () => setIsHovered(false);

    return (
        <div
            className={cn(
                'group relative overflow-hidden rounded-xl border p-6 transition-all duration-300 hover:shadow-md hover:shadow-neutral-900/5 dark:hover:shadow-black/5',
                colors.bg,
                colors.border,
                className,
            )}
            onMouseMove={handleMouseMove}
            onMouseEnter={handleMouseEnter}
            onMouseLeave={handleMouseLeave}
        >
            {/* Grid Background Pattern */}
            <div className="pointer-events-none">
                <div className="absolute inset-0 [mask-image:linear-gradient(white,transparent)] transition duration-300 group-hover:opacity-50">
                    <GridPattern
                        width={72}
                        height={56}
                        x="50%"
                        y={4}
                        squares={[
                            [4, 3],
                            [2, 1],
                            [7, 3],
                            [10, 6],
                        ]}
                        className={cn(
                            'absolute inset-x-0 inset-y-[-30%] h-[160%] w-full skew-y-[-18deg] transition-colors duration-300',
                            colors.patternFill,
                            colors.patternStroke,
                        )}
                    />
                </div>

                {/* Mouse-following gradient overlay */}
                <div
                    className={cn(
                        'absolute inset-0 rounded-xl bg-gradient-to-r opacity-0 transition duration-300',
                        colors.hoverGradient,
                        isHovered && 'opacity-100',
                    )}
                    style={{
                        maskImage: `radial-gradient(180px at ${mousePosition.x}px ${mousePosition.y}px, white, transparent)`,
                        WebkitMaskImage: `radial-gradient(180px at ${mousePosition.x}px ${mousePosition.y}px, white, transparent)`,
                    }}
                />

                {/* Enhanced pattern overlay on hover */}
                <div
                    className={cn('absolute inset-0 rounded-xl opacity-0 mix-blend-overlay transition duration-300', isHovered && 'opacity-100')}
                    style={{
                        maskImage: `radial-gradient(180px at ${mousePosition.x}px ${mousePosition.y}px, white, transparent)`,
                        WebkitMaskImage: `radial-gradient(180px at ${mousePosition.x}px ${mousePosition.y}px, white, transparent)`,
                    }}
                >
                    <GridPattern
                        width={72}
                        height={56}
                        x="50%"
                        y={4}
                        squares={[
                            [4, 3],
                            [2, 1],
                            [7, 3],
                            [10, 6],
                        ]}
                        className={cn(
                            'absolute inset-x-0 inset-y-[-30%] h-[160%] w-full skew-y-[-18deg]',
                            colors.patternHoverFill,
                            colors.patternHoverStroke,
                        )}
                    />
                </div>
            </div>

            {/* Ring overlay */}
            <div className="absolute inset-0 rounded-xl ring-1 ring-neutral-900/7.5 transition duration-300 ring-inset group-hover:ring-neutral-900/10 dark:ring-white/10 dark:group-hover:ring-white/20" />

            {/* Content */}
            <div className="relative z-10 flex items-center justify-between">
                <div className="flex-1">
                    <p className="text-sm font-medium text-neutral-600 dark:text-neutral-400">{title}</p>
                    <p className={cn('mt-2 text-3xl font-bold', colors.count)}>{count.toLocaleString()}</p>
                    {trend && (
                        <div className="mt-2 flex items-center text-sm">
                            <span
                                className={cn(
                                    'font-medium',
                                    trend.isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400',
                                )}
                            >
                                {trend.isPositive ? '+' : ''}
                                {trend.value}%
                            </span>
                            <span className="ml-1 text-neutral-500 dark:text-neutral-400">vs last week</span>
                        </div>
                    )}
                </div>
                <div
                    className={cn(
                        'flex h-12 w-12 items-center justify-center rounded-full transition-transform duration-200 hover:scale-110',
                        colors.iconBg,
                    )}
                >
                    <Icon className={cn('h-6 w-6 animate-pulse', colors.icon)} />
                </div>
            </div>
        </div>
    );
}

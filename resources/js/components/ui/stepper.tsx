import { CheckCircle, LucideIcon, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface StepperStep {
    key: string;
    title: string;
    description?: string;
    icon: LucideIcon;
    completed: boolean;
    disabled?: boolean;
    loading?: boolean;
}

export interface StepperProps {
    steps: StepperStep[];
    currentStep: string;
    orientation?: 'horizontal' | 'vertical';
    showConnectors?: boolean;
    variant?: 'default' | 'compact';
    className?: string;
    onStepClick?: (stepKey: string) => void;
}

export function Stepper({
    steps,
    currentStep,
    orientation = 'vertical',
    showConnectors = true,
    variant = 'default',
    className,
    onStepClick,
}: StepperProps) {
    const isHorizontal = orientation === 'horizontal';
    const isCompact = variant === 'compact';

    const getStepIndex = (stepKey: string) => steps.findIndex(step => step.key === stepKey);
    const currentStepIndex = getStepIndex(currentStep);

    const getStepStatus = (step: StepperStep, index: number) => {
        if (step.completed) return 'completed';
        if (step.key === currentStep) return 'active';
        if (index < currentStepIndex) return 'completed';
        return 'pending';
    };

    const StepIcon = ({ step, status }: { step: StepperStep; status: string }) => {
        const Icon = step.icon;
        const isClickable = Boolean(onStepClick && !step.disabled);

        const iconClasses = cn(
            'flex h-12 w-12 items-center justify-center rounded-full transition-all duration-300 border-2',
            {
                'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/30 dark:border-emerald-800': status === 'completed',
                'bg-blue-50 border-blue-200 dark:bg-blue-900/30 dark:border-blue-800': status === 'active',
                'bg-gray-50 border-gray-200 dark:bg-gray-800 dark:border-gray-700': status === 'pending',
                'cursor-pointer hover:scale-105 hover:shadow-md': isClickable,
                'h-8 w-8': isCompact,
            }
        );

        const content = status === 'completed' ? (
            <CheckCircle className={cn('text-emerald-600 dark:text-emerald-400', isCompact ? 'h-4 w-4' : 'h-6 w-6')} />
        ) : step.loading ? (
            <Loader2 className={cn('animate-spin text-blue-600 dark:text-blue-400', isCompact ? 'h-4 w-4' : 'h-6 w-6')} />
        ) : (
            <Icon
                className={cn('transition-colors', isCompact ? 'h-4 w-4' : 'h-6 w-6', {
                    'text-blue-600 dark:text-blue-400': status === 'active',
                    'text-gray-400 dark:text-gray-500': status === 'pending',
                })}
            />
        );

        return (
            <div
                className={iconClasses}
                onClick={isClickable ? () => onStepClick?.(step.key) : undefined}
                role={isClickable ? 'button' : undefined}
                tabIndex={isClickable ? 0 : undefined}
                aria-label={isClickable ? `Go to ${step.title}` : undefined}
                onKeyDown={
                    isClickable
                        ? (e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                onStepClick?.(step.key);
                            }
                        }
                        : undefined
                }
            >
                {content}
            </div>
        );
    };

    const StepConnector = ({ index, status }: { index: number; status: string }) => {
        if (!showConnectors || index === steps.length - 1) return null;

        const connectorClasses = cn(
            'transition-colors duration-300',
            {
                'bg-emerald-300 dark:bg-emerald-600': status === 'completed',
                'bg-blue-300 dark:bg-blue-600': status === 'active',
                'bg-gray-300 dark:bg-gray-600': status === 'pending',
            },
            isHorizontal ? 'h-0.5 flex-1 mx-4' : 'w-0.5 h-8 mx-auto'
        );

        return <div className={connectorClasses} aria-hidden="true" />;
    };

    const StepContent = ({ step, status, isClickable }: { step: StepperStep; status: string; isClickable: boolean }) => (
        <div
            className={cn('flex-1', {
                'cursor-pointer': isClickable,
                'text-center': isHorizontal,
            })}
            onClick={isClickable ? () => onStepClick?.(step.key) : undefined}
            role={isClickable ? 'button' : undefined}
            tabIndex={isClickable ? 0 : undefined}
            onKeyDown={
                isClickable
                    ? (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            onStepClick?.(step.key);
                        }
                    }
                    : undefined
            }
        >
            <h3
                className={cn('font-semibold transition-colors', {
                    'text-emerald-700 dark:text-emerald-300': status === 'completed',
                    'text-blue-700 dark:text-blue-300': status === 'active',
                    'text-gray-500 dark:text-gray-400': status === 'pending',
                    'text-sm': isCompact,
                    'text-lg': !isCompact,
                })}
            >
                {step.title}
            </h3>
            {step.description && !isCompact && (
                <p
                    className={cn('mt-1 text-sm transition-colors', {
                        'text-emerald-600 dark:text-emerald-400': status === 'completed',
                        'text-blue-600 dark:text-blue-400': status === 'active',
                        'text-gray-400 dark:text-gray-500': status === 'pending',
                    })}
                >
                    {step.description}
                </p>
            )}
        </div>
    );

    return (
        <nav
            className={cn('stepper', className)}
            aria-label="Progress steps"
            role="navigation"
        >
            <ol
                className={cn('flex', {
                    'flex-col space-y-6': !isHorizontal,
                    'flex-row items-start justify-between': isHorizontal,
                    'space-y-3': isCompact && !isHorizontal,
                })}
            >
                {steps.map((step, index) => {
                    const status = getStepStatus(step, index);
                    const isClickable = Boolean(onStepClick && !step.disabled);

                    return (
                        <li
                            key={step.key}
                            className={cn('flex', {
                                'items-center': !isHorizontal,
                                'flex-col items-center flex-1': isHorizontal,
                                'gap-4': !isCompact && !isHorizontal,
                                'gap-2': (isCompact && !isHorizontal) || isHorizontal,
                            })}
                            aria-current={status === 'active' ? 'step' : undefined}
                        >
                            {isHorizontal ? (
                                <>
                                    <div className="flex flex-col items-center gap-2">
                                        <StepIcon step={step} status={status} />
                                        <StepContent step={step} status={status} isClickable={isClickable} />
                                    </div>
                                    <StepConnector index={index} status={status} />
                                </>
                            ) : (
                                <>
                                    <div className="flex flex-col items-center">
                                        <StepIcon step={step} status={status} />
                                        <StepConnector index={index} status={status} />
                                    </div>
                                    <StepContent step={step} status={status} isClickable={isClickable} />
                                </>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

export default Stepper;

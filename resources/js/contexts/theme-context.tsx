import { createContext, ReactNode, useContext, useEffect, useState } from 'react';

type Theme = 'light' | 'dark' | 'system';
type ResolvedTheme = 'light' | 'dark';

interface ThemeContextType {
    theme: Theme;
    resolvedTheme: ResolvedTheme;
    toggleTheme: () => void;
    setTheme: (theme: Theme) => void;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

interface ThemeProviderProps {
    children: ReactNode;
}

/**
 * Theme provider that supports light, dark, and system themes
 * Integrates with Tailwind CSS dark mode classes
 */
export function ThemeProvider({ children }: ThemeProviderProps) {
    const [theme, setThemeState] = useState<Theme>(() => {
        try {
            const stored = localStorage.getItem('helpdesk_theme');
            if (stored && ['light', 'dark', 'system'].includes(stored)) {
                return stored as Theme;
            }
        } catch (error) {
            console.warn('Failed to read theme from localStorage:', error);
        }
        return 'system'; // Default to system preference
    });

    const [resolvedTheme, setResolvedTheme] = useState<ResolvedTheme>('light');

    // Function to get system theme preference
    const getSystemTheme = (): ResolvedTheme => {
        if (typeof window !== 'undefined' && window.matchMedia) {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return 'light';
    };

    // Update resolved theme when theme changes or system preference changes
    useEffect(() => {
        const updateResolvedTheme = () => {
            const newResolvedTheme = theme === 'system' ? getSystemTheme() : theme;
            setResolvedTheme(newResolvedTheme);
        };

        updateResolvedTheme();

        // Listen for system theme changes
        if (typeof window !== 'undefined' && window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            const handleChange = () => {
                if (theme === 'system') {
                    updateResolvedTheme();
                }
            };

            mediaQuery.addEventListener('change', handleChange);
            return () => mediaQuery.removeEventListener('change', handleChange);
        }
    }, [theme]);

    // Apply theme to document
    useEffect(() => {
        try {
            localStorage.setItem('helpdesk_theme', theme);
        } catch (error) {
            console.warn('Failed to save theme to localStorage:', error);
        }

        // Update document class for Tailwind CSS
        const root = document.documentElement;

        if (resolvedTheme === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }

        // Set color-scheme CSS property for native form controls
        root.style.colorScheme = resolvedTheme;

        // Optional: Emit custom event for other components to listen to
        window.dispatchEvent(
            new CustomEvent('themechange', {
                detail: { theme, resolvedTheme },
            }),
        );
    }, [theme, resolvedTheme]);

    const setTheme = (newTheme: Theme) => {
        setThemeState(newTheme);
    };

    const toggleTheme = () => {
        setThemeState((currentTheme) => {
            // Cycle through: light -> dark -> system -> light
            switch (currentTheme) {
                case 'light':
                    return 'dark';
                case 'dark':
                    return 'system';
                case 'system':
                    return 'light';
                default:
                    return 'light';
            }
        });
    };

    const value: ThemeContextType = {
        theme,
        resolvedTheme,
        toggleTheme,
        setTheme,
    };

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

/**
 * Hook to use theme context
 */
export function useTheme(): ThemeContextType {
    const context = useContext(ThemeContext);
    if (!context) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }
    return context;
}

/**
 * Hook to get only the resolved theme (light/dark)
 * Useful when you only need to know the actual theme being displayed
 */
export function useResolvedTheme(): ResolvedTheme {
    const { resolvedTheme } = useTheme();
    return resolvedTheme;
}

/**
 * Component for theme toggle button
 */
interface ThemeToggleProps {
    className?: string;
    showLabel?: boolean;
}

export function ThemeToggle({ className = '', showLabel = false }: ThemeToggleProps) {
    const { theme, toggleTheme } = useTheme();

    const getThemeIcon = () => {
        switch (theme) {
            case 'light':
                return '☀️';
            case 'dark':
                return '🌙';
            case 'system':
                return '💻';
            default:
                return '☀️';
        }
    };

    const getThemeLabel = () => {
        switch (theme) {
            case 'light':
                return 'Light';
            case 'dark':
                return 'Dark';
            case 'system':
                return 'System';
            default:
                return 'Light';
        }
    };

    return (
        <button
            onClick={toggleTheme}
            className={`inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-gray-100 dark:hover:bg-gray-800 ${className}`}
            title={`Current theme: ${getThemeLabel()}. Click to cycle themes.`}
            aria-label={`Switch theme (currently ${getThemeLabel()})`}
        >
            <span className="text-lg" role="img" aria-hidden="true">
                {getThemeIcon()}
            </span>
            {showLabel && <span>{getThemeLabel()}</span>}
        </button>
    );
}

/**
 * Hook to apply theme-aware styles
 */
export function useThemeStyles() {
    const { resolvedTheme } = useTheme();

    return {
        // Common style utilities that adapt to theme
        cardBg: resolvedTheme === 'dark' ? 'bg-gray-800' : 'bg-white',
        textPrimary: resolvedTheme === 'dark' ? 'text-gray-100' : 'text-gray-900',
        textSecondary: resolvedTheme === 'dark' ? 'text-gray-300' : 'text-gray-600',
        border: resolvedTheme === 'dark' ? 'border-gray-700' : 'border-gray-200',
        inputBg: resolvedTheme === 'dark' ? 'bg-gray-700' : 'bg-white',
        hoverBg: resolvedTheme === 'dark' ? 'hover:bg-gray-700' : 'hover:bg-gray-50',
    };
}

/**
 * CSS-in-JS styles for components that need dynamic theme support
 */
export const themeStyles = {
    light: {
        '--color-bg-primary': '#ffffff',
        '--color-bg-secondary': '#f9fafb',
        '--color-text-primary': '#111827',
        '--color-text-secondary': '#6b7280',
        '--color-border': '#e5e7eb',
    },
    dark: {
        '--color-bg-primary': '#1f2937',
        '--color-bg-secondary': '#111827',
        '--color-text-primary': '#f9fafb',
        '--color-text-secondary': '#d1d5db',
        '--color-border': '#374151',
    },
} as const;

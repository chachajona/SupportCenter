import { useEffect, useState } from 'react';

interface FilterState {
    search?: string;
    department_id?: number;
    status_id?: number;
    priority_id?: number;
    assigned_to?: number;
    date_from?: string;
    date_to?: string;
    per_page?: number;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

interface UsePersistentFiltersReturn {
    filters: FilterState;
    updateFilter: <K extends keyof FilterState>(key: K, value: FilterState[K]) => void;
    clearFilters: () => void;
    resetFilter: <K extends keyof FilterState>(key: K) => void;
    hasActiveFilters: boolean;
    activeFilterCount: number;
}

/**
 * Hook for managing persistent filter state in localStorage
 * Maintains filter state across page refreshes and navigation
 */
export function usePersistentFilters(key: string, defaultFilters: FilterState = {}): UsePersistentFiltersReturn {
    const storageKey = `helpdesk_filters_${key}`;

    const [filters, setFilters] = useState<FilterState>(() => {
        try {
            const saved = localStorage.getItem(storageKey);
            if (saved) {
                const parsedFilters = JSON.parse(saved);
                // Merge with defaults to handle new filter options
                return { ...defaultFilters, ...parsedFilters };
            }
        } catch (error) {
            console.warn('Failed to parse saved filters:', error);
        }
        return defaultFilters;
    });

    // Save filters to localStorage whenever they change
    useEffect(() => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(filters));
        } catch (error) {
            console.warn('Failed to save filters to localStorage:', error);
        }
    }, [filters, storageKey]);

    const updateFilter = <K extends keyof FilterState>(key: K, value: FilterState[K]) => {
        setFilters((prev) => {
            // Remove undefined/null values to keep localStorage clean
            const newFilters = { ...prev };
            if (value === undefined || value === null || value === '') {
                delete newFilters[key];
            } else {
                newFilters[key] = value;
            }
            return newFilters;
        });
    };

    const clearFilters = () => {
        setFilters(defaultFilters);
        try {
            localStorage.removeItem(storageKey);
        } catch (error) {
            console.warn('Failed to clear filters from localStorage:', error);
        }
    };

    const resetFilter = <K extends keyof FilterState>(key: K) => {
        setFilters((prev) => {
            const newFilters = { ...prev };
            delete newFilters[key];
            return newFilters;
        });
    };

    // Calculate if there are active filters (excluding pagination and sorting)
    const hasActiveFilters = Object.keys(filters).some(
        (key) =>
            !['per_page', 'sort_by', 'sort_direction'].includes(key) &&
            filters[key as keyof FilterState] !== undefined &&
            filters[key as keyof FilterState] !== '',
    );

    const activeFilterCount = Object.keys(filters).filter(
        (key) =>
            !['per_page', 'sort_by', 'sort_direction'].includes(key) &&
            filters[key as keyof FilterState] !== undefined &&
            filters[key as keyof FilterState] !== '',
    ).length;

    return {
        filters,
        updateFilter,
        clearFilters,
        resetFilter,
        hasActiveFilters,
        activeFilterCount,
    };
}

/**
 * Hook specifically for ticket filters with predefined defaults
 */
export function useTicketFilters() {
    return usePersistentFilters('tickets', {
        per_page: 25,
        sort_by: 'created_at',
        sort_direction: 'desc' as const,
    });
}

/**
 * Hook for knowledge base article filters
 */
export function useKnowledgeBaseFilters() {
    return usePersistentFilters('knowledge_base', {
        per_page: 20,
        sort_by: 'title',
        sort_direction: 'asc' as const,
    });
}

/**
 * Hook for user management filters
 */
export function useUserFilters() {
    return usePersistentFilters('users', {
        per_page: 50,
        sort_by: 'name',
        sort_direction: 'asc' as const,
    });
}

/**
 * Utility function to convert filters to URL search params
 */
export function filtersToSearchParams(filters: FilterState): URLSearchParams {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            params.append(key, String(value));
        }
    });

    return params;
}

/**
 * Utility function to parse URL search params to filters
 */
export function searchParamsToFilters(searchParams: URLSearchParams): FilterState {
    // Use a generic record so we can assign dynamic keys without resorting to `any`
    const filters: Record<string, unknown> = {};

    // Keys whose values should be converted to numbers
    const numericKeys = ['department_id', 'status_id', 'priority_id', 'assigned_to', 'per_page'] as const;

    for (const [key, value] of searchParams.entries()) {
        if (value === '') {
            continue;
        }

        if ((numericKeys as readonly string[]).includes(key)) {
            const numValue = parseInt(value, 10);
            if (!isNaN(numValue)) {
                filters[key] = numValue;
            }
        } else {
            filters[key] = value;
        }
    }

    return filters as FilterState;
}

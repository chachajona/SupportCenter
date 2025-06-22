import { User } from './auth';

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User;
    };
    ziggy: {
        location: string;
        port: number | null;
        query: Record<string, string>;
        url: string;
    };
};

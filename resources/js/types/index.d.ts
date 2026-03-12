export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role: "admin" | "doctor" | "nurse" | "registration" | "pharmacist";
    phone?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash: {
        success?: string;
        error?: string;
    };
    ziggy: {
        location: string;
        url: string;
        port: number | null;
        defaults: Record<string, unknown>;
        routes: Record<string, unknown>;
    };
};

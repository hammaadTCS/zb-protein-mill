/** The logged-in user as shared by the server (conventions §10). */
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    roles: string[];
    permissions: string[];
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

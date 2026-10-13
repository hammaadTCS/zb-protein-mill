import type { ReactNode } from 'react';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    mark,
    children,
}: {
    title?: string;
    description?: string;
    mark?: ReactNode;
    children: ReactNode;
}) {
    return (
        <AuthLayoutTemplate title={title} description={description} mark={mark}>
            {children}
        </AuthLayoutTemplate>
    );
}

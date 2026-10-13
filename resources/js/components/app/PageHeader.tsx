import type { ReactNode } from 'react';

/** The title row of every app page; page titles never repeat the company name (spec §15.7). */
export function PageHeader({
    title,
    children,
}: {
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <h1 className="text-xl font-semibold text-brand-deep dark:text-foreground">
                {title}
            </h1>
            {children && (
                <div className="flex flex-wrap items-center gap-2">
                    {children}
                </div>
            )}
        </div>
    );
}

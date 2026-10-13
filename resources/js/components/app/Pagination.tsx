import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

/** Previous / next for lists of 50 rows (spec §11.2). */
export function Pagination({
    page,
}: {
    page: Omit<Paginated<unknown>, 'data'>;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-2 text-sm">
            <span className="text-muted-foreground tabular-nums">
                Page {page.current_page} of {page.last_page}
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!page.prev_page_url}
                    asChild={!!page.prev_page_url}
                >
                    {page.prev_page_url ? (
                        <Link href={page.prev_page_url} preserveScroll>
                            Previous
                        </Link>
                    ) : (
                        'Previous'
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!page.next_page_url}
                    asChild={!!page.next_page_url}
                >
                    {page.next_page_url ? (
                        <Link href={page.next_page_url} preserveScroll>
                            Next
                        </Link>
                    ) : (
                        'Next'
                    )}
                </Button>
            </div>
        </div>
    );
}

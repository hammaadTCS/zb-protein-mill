import { ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { search } from '@/routes/parties';

export type PartyType = 'supplier' | 'buyer' | 'partner' | 'employee' | 'other';

export type PartyOption = {
    id: number;
    code: string;
    type: PartyType;
    /** "Name – Area – Mobile" (BR-PTY-09). */
    label: string;
};

/**
 * Spec §15.4, BR-PTY-01, BR-PTY-09: every entry picks its party from the list.
 * Searches active parties of the given types by name, Urdu name, code and mobile.
 * Built from the kit's dialog and input (no extra package).
 */
export function PartyPicker({
    id,
    types,
    value,
    onChange,
    placeholder = 'Select party',
    invalid = false,
}: {
    id?: string;
    types: PartyType[];
    value: PartyOption | null;
    onChange: (party: PartyOption | null) => void;
    placeholder?: string;
    invalid?: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                id={id}
                type="button"
                variant="outline"
                aria-invalid={invalid}
                className={cn(
                    'h-11 w-full justify-between font-normal',
                    !value && 'text-muted-foreground',
                )}
                onClick={() => setOpen(true)}
            >
                <span className="truncate">{value?.label ?? placeholder}</span>
                <ChevronsUpDown className="size-4 opacity-50" />
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="top-4 translate-y-0 sm:top-[20%]">
                    <DialogHeader>
                        <DialogTitle>{placeholder}</DialogTitle>
                    </DialogHeader>
                    {open && (
                        <PartySearch
                            types={types}
                            onPick={(party) => {
                                onChange(party);
                                setOpen(false);
                            }}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function PartySearch({
    types,
    onPick,
}: {
    types: PartyType[];
    onPick: (party: PartyOption) => void;
}) {
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<PartyOption[]>([]);
    const [active, setActive] = useState(0);
    const [loading, setLoading] = useState(true);
    const typesKey = types.join(',');
    const listRef = useRef<HTMLUListElement>(null);

    useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            fetch(
                search.url({
                    query: { q: query, types: typesKey.split(',') },
                }),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            )
                .then((response) => response.json())
                .then((body: { data: PartyOption[] }) => {
                    setOptions(body.data);
                    setActive(0);
                    setLoading(false);
                })
                .catch(() => undefined);
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, typesKey]);

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => Math.min(index + 1, options.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter' && options[active]) {
            event.preventDefault();
            onPick(options[active]);
        }
    };

    return (
        <div className="grid gap-3">
            <Input
                autoFocus
                value={query}
                placeholder="Name, code or mobile"
                onChange={(event) => setQuery(event.target.value)}
                onKeyDown={onKeyDown}
                role="combobox"
                aria-expanded
                aria-controls="party-options"
                className="h-11"
            />
            <ul
                id="party-options"
                ref={listRef}
                role="listbox"
                className="max-h-80 overflow-y-auto rounded-md border"
            >
                {options.map((option, index) => (
                    <li
                        key={option.id}
                        role="option"
                        aria-selected={index === active}
                        className={cn(
                            'cursor-pointer px-3 py-3 text-sm',
                            index === active && 'bg-accent',
                        )}
                        onMouseEnter={() => setActive(index)}
                        onClick={() => onPick(option)}
                    >
                        <span className="font-medium">{option.label}</span>{' '}
                        <span className="text-muted-foreground">
                            {option.code}
                        </span>
                    </li>
                ))}
                {!loading && options.length === 0 && (
                    <li className="px-3 py-3 text-sm text-muted-foreground">
                        No party found.
                    </li>
                )}
            </ul>
        </div>
    );
}

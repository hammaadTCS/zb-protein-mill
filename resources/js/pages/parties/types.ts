import type { PartyType } from '@/components/app/PartyPicker';

export const PARTY_TYPES: { value: PartyType; label: string }[] = [
    { value: 'supplier', label: 'Supplier' },
    { value: 'buyer', label: 'Buyer' },
    { value: 'partner', label: 'Partner' },
    { value: 'employee', label: 'Employee' },
    { value: 'other', label: 'Other' },
];

export const PARTY_TYPE_LABELS: Record<PartyType, string> = Object.fromEntries(
    PARTY_TYPES.map(({ value, label }) => [value, label]),
) as Record<PartyType, string>;

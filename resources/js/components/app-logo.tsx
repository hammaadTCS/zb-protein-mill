import AppLogoIcon from '@/components/app-logo-icon';

/** Sidebar header: the monogram at 32 px and the company name (spec §15.7). */
export default function AppLogo() {
    return (
        <>
            <AppLogoIcon className="size-8 shrink-0 rounded-md" />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">
                    ZB Poultry Protein Mill
                </span>
            </div>
        </>
    );
}

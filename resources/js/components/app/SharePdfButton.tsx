import { Share2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Card T6.10, D-35: "Share PDF" sends the statement or slip through the phone's share
 * sheet (Web Share API with the file) where the browser can, and downloads it
 * otherwise.
 */
export function SharePdfButton({
    href,
    filename,
}: {
    href: string;
    filename: string;
}) {
    const [busy, setBusy] = useState(false);

    const share = async () => {
        setBusy(true);

        try {
            const response = await fetch(href, {
                credentials: 'same-origin',
                headers: { Accept: 'application/pdf' },
            });

            if (!response.ok) {
                // Let the browser show the server's error page.
                window.location.assign(href);

                return;
            }

            const blob = await response.blob();
            const file = new File([blob], filename, {
                type: 'application/pdf',
            });

            if (navigator.canShare?.({ files: [file] })) {
                await navigator.share({ files: [file], title: filename });

                return;
            }

            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.click();
            URL.revokeObjectURL(url);
        } catch (error) {
            // Closing the share sheet is not an error.
            if (
                !(error instanceof DOMException && error.name === 'AbortError')
            ) {
                window.location.assign(href);
            }
        } finally {
            setBusy(false);
        }
    };

    return (
        <Button variant="outline" onClick={share} disabled={busy}>
            <Share2 />
            Share PDF
        </Button>
    );
}

import { Share2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';

/**
 * Card T6.10, D-35: "Share PDF" sends the statement or slip through the phone's share
 * sheet (Web Share API with the file) where the browser can, and downloads it
 * otherwise. A refused request (for example a statement over 3 months, BR-RPT-11)
 * shows the server's message instead of saving a page as the file.
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
                // JSON first: a validation error comes back as 422 with its message.
                headers: { Accept: 'application/json, application/pdf' },
            });

            if (response.status === 422) {
                const body = (await response.json()) as { message?: string };

                if (body.message) {
                    toast.error(body.message);

                    return;
                }
            }

            const type = response.headers.get('Content-Type') ?? '';

            if (!response.ok || !type.startsWith('application/pdf')) {
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

/**
 * Spec frontend §5, T2.8: photos are resized in the browser before upload. A JPG,
 * PNG or WEBP photo longer than 2000 px on its longest side is drawn at 2000 px and
 * sent as JPEG quality 0.85; smaller images are sent as they are. The server checks
 * and re-encodes every image again (BR-SEC-06).
 */
export const MAX_SIDE = 2000;

const RESIZABLE = ['image/jpeg', 'image/png', 'image/webp'];

/** The size to draw at, keeping the proportions; null when no resize is needed. */
export function scaledSize(
    width: number,
    height: number,
    maxSide: number = MAX_SIDE,
): { width: number; height: number } | null {
    const longest = Math.max(width, height);

    if (longest <= maxSide) {
        return null;
    }

    const scale = maxSide / longest;

    return {
        width: Math.max(1, Math.round(width * scale)),
        height: Math.max(1, Math.round(height * scale)),
    };
}

export async function prepareImage(file: File): Promise<File> {
    if (!RESIZABLE.includes(file.type)) {
        return file;
    }

    const bitmap = await createImageBitmap(file);
    const size = scaledSize(bitmap.width, bitmap.height);

    if (!size) {
        bitmap.close();

        return file;
    }

    const canvas = document.createElement('canvas');
    canvas.width = size.width;
    canvas.height = size.height;
    canvas.getContext('2d')?.drawImage(bitmap, 0, 0, size.width, size.height);
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', 0.85),
    );

    if (!blob) {
        return file;
    }

    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';

    return new File([blob], name, { type: 'image/jpeg' });
}

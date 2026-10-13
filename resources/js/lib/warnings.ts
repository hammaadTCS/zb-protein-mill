/**
 * Conventions §5: the server answers an unconfirmed warning with HTTP 422 and
 * {"warning": {code, message, details}}. This reads it from a failed visit.
 */
export type ServerWarning = {
    code: string;
    message: string;
    details: Record<string, unknown>;
};

type HttpExceptionResponse = {
    status: number;
    data: string | Record<string, unknown>;
};

export function readWarning(
    response: HttpExceptionResponse,
): ServerWarning | null {
    if (response.status !== 422) {
        return null;
    }

    let body: unknown = response.data;

    if (typeof body === 'string') {
        try {
            body = JSON.parse(body);
        } catch {
            return null;
        }
    }

    const warning = (body as { warning?: ServerWarning } | null)?.warning;

    return warning && typeof warning.code === 'string' ? warning : null;
}

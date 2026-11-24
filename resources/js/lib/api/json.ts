import { isRecord } from '../isRecord';

export async function readJsonObject(response: Response): Promise<Record<string, unknown> | undefined> {
    try {
        const payload: unknown = await response.json();
        return isRecord(payload) ? payload : undefined;
    } catch (error) {
        if (!(error instanceof SyntaxError)) throw error;
    }
}

import { csrfHeaders } from './csrf';
import { readJsonObject } from './json';

export async function generateVoicePreview(url: string, voiceId: string, text: string, version: number, signal: AbortSignal): Promise<Blob> {
    const response = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ voice_id: voiceId, text, lock_version: version }),
        signal,
    });
    if (response.ok && response.headers.get('Content-Type')?.startsWith('audio/wav')) return response.blob();
    if (response.status === 429) throw new Error('Слишком много проб подряд. Попробуйте через минуту.');
    const payload = await readJsonObject(response);
    throw new Error(typeof payload?.message === 'string' ? payload.message : 'Не удалось получить пробу. Проверьте соединение.');
}

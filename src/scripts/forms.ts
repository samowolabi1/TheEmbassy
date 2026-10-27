export type SubmitResult = 'ok' | 'error' | 'unconfigured';

/**
 * POSTs a form's data as JSON. Returns 'unconfigured' when no endpoint is set,
 * so a form never claims to have sent something it did not.
 */
export async function submitJson(endpoint: string | undefined, data: Record<string, unknown>): Promise<SubmitResult> {
  if (!endpoint) return 'unconfigured';
  try {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(data),
    });
    return res.ok ? 'ok' : 'error';
  } catch {
    return 'error';
  }
}

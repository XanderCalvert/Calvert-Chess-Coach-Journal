import { cookies } from 'next/headers'

export async function getLaravelHeaders(): Promise<Record<string, string>> {
  const cookieStore = await cookies()
  const token = cookieStore.get('chess_token')?.value
  return {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  }
}

export function getLaravelBaseUrl(): string {
  const url = process.env.LARAVEL_API_URL
  if (!url) throw new Error('LARAVEL_API_URL is not configured')
  return url
}

// Call from proxy routes that receive a 401 from Laravel.
// Clears the stale cookie so the next middleware redirect goes to /login.
export async function clearAuthCookie(): Promise<void> {
  const cookieStore = await cookies()
  cookieStore.delete('chess_token')
}

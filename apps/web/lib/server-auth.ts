import { cookies } from 'next/headers'
import { redirect } from 'next/navigation'
import type { AuthUser } from './auth-context'

async function fetchMe(token: string): Promise<AuthUser | null> {
  const laravelUrl = process.env.LARAVEL_API_URL
  if (!laravelUrl) return null
  try {
    const res = await fetch(`${laravelUrl}/api/v1/auth/me`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      cache: 'no-store',
    })
    if (!res.ok) return null
    return res.json()
  } catch {
    return null
  }
}

/**
 * Server-side helper for pages that require a logged-in user with at least one
 * connected chess account. Redirects to /login or /onboarding as appropriate.
 */
export async function getRequiredUser(): Promise<AuthUser> {
  const cookieStore = await cookies()
  const token = cookieStore.get('chess_token')?.value
  if (!token) redirect('/login')

  const user = await fetchMe(token)
  if (!user) redirect('/login')
  if (!user.has_connected_accounts) redirect('/onboarding')

  return user
}

/**
 * Server-side helper for the onboarding page: returns the user if logged in,
 * redirecting to /login if not. Does NOT redirect based on account status —
 * the onboarding page handles that itself.
 */
export async function getAuthenticatedUser(): Promise<AuthUser | null> {
  const cookieStore = await cookies()
  const token = cookieStore.get('chess_token')?.value
  if (!token) return null

  return fetchMe(token)
}

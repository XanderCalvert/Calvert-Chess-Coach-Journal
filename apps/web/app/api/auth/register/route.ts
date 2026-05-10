import { NextRequest, NextResponse } from 'next/server'
import { cookies } from 'next/headers'

export async function POST(request: NextRequest) {
  const origin = request.headers.get('origin')
  const host = request.headers.get('host')
  if (origin && host && !origin.endsWith(host)) {
    return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
  }

  const laravelUrl = process.env.LARAVEL_API_URL
  if (!laravelUrl) {
    return NextResponse.json({ error: 'LARAVEL_API_URL is not configured' }, { status: 503 })
  }

  let body: unknown
  try {
    body = await request.json()
  } catch {
    return NextResponse.json({ error: 'Invalid JSON' }, { status: 400 })
  }

  let res: Response
  try {
    res = await fetch(`${laravelUrl}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    })
  } catch (err) {
    return NextResponse.json(
      { error: `Could not reach API: ${err instanceof Error ? err.message : String(err)}` },
      { status: 502 },
    )
  }

  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    return NextResponse.json(data, { status: res.status })
  }

  const { token, user } = await res.json()

  const cookieStore = await cookies()
  cookieStore.set('chess_token', token, {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    secure: process.env.NODE_ENV === 'production',
    maxAge: 60 * 60 * 24 * 7,
  })

  return NextResponse.json({ user }, { status: 201 })
}

import { NextRequest, NextResponse } from 'next/server'
import { cookies } from 'next/headers'
import { getLaravelHeaders, getLaravelBaseUrl } from '@/lib/apiClient'

export async function POST(request: NextRequest) {
  const origin = request.headers.get('origin')
  const host = request.headers.get('host')
  if (origin && host && !origin.endsWith(host)) {
    return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
  }

  const headers = await getLaravelHeaders()

  try {
    await fetch(`${getLaravelBaseUrl()}/api/v1/auth/logout`, {
      method: 'POST',
      headers,
    })
  } catch {
    // Best-effort — always clear the cookie regardless.
  }

  const cookieStore = await cookies()
  cookieStore.delete('chess_token')

  return new NextResponse(null, { status: 204 })
}

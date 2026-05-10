import { NextResponse } from 'next/server'
import { getLaravelHeaders, getLaravelBaseUrl, clearAuthCookie } from '@/lib/apiClient'

export async function GET() {
  const headers = await getLaravelHeaders()

  let res: Response
  try {
    res = await fetch(`${getLaravelBaseUrl()}/api/v1/auth/me`, { headers })
  } catch (err) {
    return NextResponse.json(
      { error: `Could not reach API: ${err instanceof Error ? err.message : String(err)}` },
      { status: 502 },
    )
  }

  if (res.status === 401) {
    await clearAuthCookie()
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  const user = await res.json()
  return NextResponse.json(user, { status: res.status })
}

import { NextRequest, NextResponse } from 'next/server'
import { getLaravelHeaders, getLaravelBaseUrl, clearAuthCookie } from '@/lib/apiClient'

export async function GET() {
  const headers = await getLaravelHeaders()

  let res: Response
  try {
    res = await fetch(`${getLaravelBaseUrl()}/api/v1/connected-accounts`, { headers })
  } catch (err) {
    return NextResponse.json(
      { error: `Could not reach Laravel API: ${err instanceof Error ? err.message : String(err)}` },
      { status: 502 },
    )
  }

  if (res.status === 401) {
    await clearAuthCookie()
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  const data = await res.json()
  return NextResponse.json(data, { status: res.status })
}

export async function POST(request: NextRequest) {
  let body: unknown
  try {
    body = await request.json()
  } catch {
    return NextResponse.json({ error: 'Invalid JSON body' }, { status: 400 })
  }

  const headers = await getLaravelHeaders()

  let res: Response
  try {
    res = await fetch(`${getLaravelBaseUrl()}/api/v1/connected-accounts`, {
      method: 'POST',
      headers,
      body: JSON.stringify(body),
    })
  } catch (err) {
    return NextResponse.json(
      { error: `Could not reach Laravel API: ${err instanceof Error ? err.message : String(err)}` },
      { status: 502 }
    )
  }

  if (res.status === 401) {
    await clearAuthCookie()
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  const data = await res.json()
  return NextResponse.json(data, { status: res.status })
}

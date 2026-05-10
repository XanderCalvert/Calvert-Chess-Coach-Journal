import { NextRequest, NextResponse } from 'next/server'
import { getLaravelHeaders, getLaravelBaseUrl, clearAuthCookie } from '@/lib/apiClient'

export async function POST(
  _request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params
  const headers = await getLaravelHeaders()

  let res: Response
  try {
    res = await fetch(`${getLaravelBaseUrl()}/api/v1/games/${id}/analyse`, {
      method: 'POST',
      headers,
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

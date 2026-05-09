import { NextRequest, NextResponse } from 'next/server'

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ platform: string; username: string }> }
) {
  const { platform, username } = await params
  const gameType = request.nextUrl.searchParams.get('game_type') ?? 'all'
  const daysParam = request.nextUrl.searchParams.get('days')
  const daysQuery =
    daysParam !== null && daysParam !== ''
      ? `&days=${encodeURIComponent(daysParam)}`
      : ''

  const laravelUrl = process.env.LARAVEL_API_URL
  if (!laravelUrl) {
    return NextResponse.json({ error: 'LARAVEL_API_URL is not configured' }, { status: 503 })
  }

  let res: Response
  try {
    res = await fetch(
      `${laravelUrl}/api/v1/connected-accounts/by-username/${platform}/${username}/stats?game_type=${encodeURIComponent(gameType)}${daysQuery}`,
      { headers: { Accept: 'application/json' } }
    )
  } catch (err) {
    return NextResponse.json(
      { error: `Could not reach Laravel API: ${err instanceof Error ? err.message : String(err)}` },
      { status: 502 }
    )
  }

  const data = await res.json()
  return NextResponse.json(data, { status: res.status })
}

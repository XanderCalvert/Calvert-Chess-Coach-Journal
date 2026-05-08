import { NextRequest, NextResponse } from 'next/server'

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ platform: string; username: string }> }
) {
  const { platform, username } = await params
  const page = request.nextUrl.searchParams.get('page') ?? '1'

  const laravelUrl = process.env.LARAVEL_API_URL
  if (!laravelUrl) {
    return NextResponse.json({ error: 'LARAVEL_API_URL is not configured' }, { status: 503 })
  }

  let res: Response
  try {
    res = await fetch(
      `${laravelUrl}/api/v1/connected-accounts/by-username/${platform}/${username}/games?page=${page}`,
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

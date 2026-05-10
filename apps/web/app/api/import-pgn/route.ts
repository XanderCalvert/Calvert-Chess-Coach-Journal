import { NextRequest, NextResponse } from 'next/server'
import { parsePgn, PgnParseError, ParsedGame, ParsedMove } from '@/lib/pgn-parser'

interface LaravelMove {
  move_number: number
  colour: 'white' | 'black'
  san: string
  uci: string
  fen_before: string
  fen_after: string
}

interface LaravelPayload {
  pgn_raw: string
  white_player: string
  black_player: string
  result: 'white' | 'black' | 'draw' | 'unknown'
  played_at: string | null
  eco_code: string
  opening_name: string
  move_count: number
  moves: LaravelMove[]
}

function tolaravelPayload(pgn: string, parsed: ParsedGame): LaravelPayload {
  return {
    pgn_raw: pgn,
    white_player: parsed.headers.white,
    black_player: parsed.headers.black,
    result: parsed.headers.result,
    played_at: parsed.headers.playedAt,
    eco_code: parsed.headers.ecoCode,
    opening_name: parsed.headers.openingName,
    move_count: parsed.moveCount,
    moves: parsed.moves.map((m: ParsedMove) => ({
      move_number: m.moveNumber,
      colour: m.colour,
      san: m.san,
      uci: m.uci,
      fen_before: m.fenBefore,
      fen_after: m.fenAfter,
    })),
  }
}

export async function POST(request: NextRequest) {
  let body: unknown
  try {
    body = await request.json()
  } catch {
    return NextResponse.json({ error: 'Request body must be JSON' }, { status: 400 })
  }

  if (
    typeof body !== 'object' ||
    body === null ||
    typeof (body as Record<string, unknown>)['pgn'] !== 'string'
  ) {
    return NextResponse.json({ error: 'Missing required field: pgn (string)' }, { status: 400 })
  }

  const pgn = (body as Record<string, unknown>)['pgn'] as string

  let parsed: ParsedGame
  try {
    parsed = parsePgn(pgn)
  } catch (err) {
    if (err instanceof PgnParseError) {
      return NextResponse.json({ error: err.message }, { status: 422 })
    }
    return NextResponse.json({ error: 'Unexpected error during parsing' }, { status: 500 })
  }

  const laravelUrl = process.env.LARAVEL_API_URL
  if (!laravelUrl) {
    return NextResponse.json({ error: 'LARAVEL_API_URL is not configured' }, { status: 503 })
  }

  const { getLaravelHeaders, clearAuthCookie } = await import('@/lib/apiClient')
  const authHeaders = await getLaravelHeaders()

  let laravelResponse: Response
  try {
    laravelResponse = await fetch(`${laravelUrl}/api/v1/games`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify(tolaravelPayload(pgn, parsed)),
    })
  } catch (err) {
    return NextResponse.json(
      { error: `Could not reach Laravel API: ${err instanceof Error ? err.message : String(err)}` },
      { status: 502 }
    )
  }

  if (laravelResponse.status === 401) {
    await clearAuthCookie()
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  if (!laravelResponse.ok) {
    const detail = await laravelResponse.json().catch(() => null)
    return NextResponse.json(
      { error: 'Laravel API rejected the payload', detail },
      { status: laravelResponse.status }
    )
  }

  const { game_id, move_count } = await laravelResponse.json()

  return NextResponse.json({
    game_id,
    move_count,
    parsed: {
      headers: parsed.headers,
      moves: parsed.moves,
    },
  })
}

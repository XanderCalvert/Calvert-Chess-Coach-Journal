import { NextRequest, NextResponse } from 'next/server'
import { parsePgn, PgnParseError } from '@/lib/pgn-parser'

export async function POST(request: NextRequest) {
  let body: unknown
  try {
    body = await request.json()
  } catch {
    return NextResponse.json({ error: 'Request body must be JSON' }, { status: 400 })
  }

  if (typeof body !== 'object' || body === null || typeof (body as Record<string, unknown>)['pgn'] !== 'string') {
    return NextResponse.json({ error: 'Missing required field: pgn (string)' }, { status: 400 })
  }

  const pgn = (body as Record<string, unknown>)['pgn'] as string

  try {
    const parsed = parsePgn(pgn)
    return NextResponse.json(parsed)
  } catch (err) {
    if (err instanceof PgnParseError) {
      return NextResponse.json({ error: err.message }, { status: 422 })
    }
    return NextResponse.json({ error: 'Unexpected error during parsing' }, { status: 500 })
  }
}

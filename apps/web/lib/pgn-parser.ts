import { Chess } from 'chess.js'

export class PgnParseError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'PgnParseError'
  }
}

export interface ParsedMove {
  moveNumber: number
  colour: 'white' | 'black'
  san: string
  uci: string
  fenBefore: string
  fenAfter: string
}

export interface ParsedHeaders {
  white: string
  black: string
  result: 'white' | 'black' | 'draw' | 'unknown'
  playedAt: string | null
  ecoCode: string
  openingName: string
  raw: Record<string, string>
}

export interface ParsedGame {
  headers: ParsedHeaders
  moves: ParsedMove[]
  moveCount: number
}

function mapResult(raw: string): 'white' | 'black' | 'draw' | 'unknown' {
  if (raw === '1-0') return 'white'
  if (raw === '0-1') return 'black'
  if (raw === '1/2-1/2') return 'draw'
  return 'unknown'
}

function mapColour(chessJsColor: 'w' | 'b'): 'white' | 'black' {
  return chessJsColor === 'w' ? 'white' : 'black'
}

function parseDate(raw: string | undefined): string | null {
  if (!raw) return null
  // PGN dates are YYYY.MM.DD — convert to ISO-like YYYY-MM-DD for downstream use
  const normalised = raw.replace(/\./g, '-')
  // Reject placeholder dates like "????.??.??"
  if (/\?/.test(normalised)) return null
  const d = new Date(normalised)
  return isNaN(d.getTime()) ? null : normalised
}

export function parsePgn(raw: string): ParsedGame {
  const chess = new Chess()

  try {
    chess.loadPgn(raw)
  } catch (err) {
    throw new PgnParseError(
      `Invalid PGN: ${err instanceof Error ? err.message : String(err)}`
    )
  }

  const rawHeaders = chess.getHeaders()
  const verboseMoves = chess.history({ verbose: true })

  if (verboseMoves.length === 0) {
    throw new PgnParseError('Invalid PGN: no moves found')
  }

  const headers: ParsedHeaders = {
    white: rawHeaders['White'] ?? 'Unknown',
    black: rawHeaders['Black'] ?? 'Unknown',
    result: mapResult(rawHeaders['Result'] ?? ''),
    playedAt: parseDate(rawHeaders['Date']),
    ecoCode: rawHeaders['ECO'] ?? '',
    openingName: rawHeaders['Opening'] ?? 'Unknown',
    raw: rawHeaders,
  }

  const moves: ParsedMove[] = verboseMoves.map((move, index) => ({
    moveNumber: index + 1,
    colour: mapColour(move.color),
    san: move.san,
    uci: move.lan,
    fenBefore: move.before,
    fenAfter: move.after,
  }))

  return { headers, moves, moveCount: moves.length }
}

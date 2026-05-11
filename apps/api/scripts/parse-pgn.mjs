import { Chess } from 'chess.js'
import { readFileSync } from 'fs'

function mapResult(raw) {
  if (raw === '1-0') return 'white'
  if (raw === '0-1') return 'black'
  if (raw === '1/2-1/2') return 'draw'
  return 'unknown'
}

function parseDate(raw) {
  if (!raw) return null
  const normalised = raw.replace(/\./g, '-')
  if (/\?/.test(normalised)) return null
  const d = new Date(normalised)
  return isNaN(d.getTime()) ? null : normalised
}

const filePath = process.argv[2]
if (!filePath) {
  process.stderr.write('Usage: node parse-pgn.mjs <pgn-file>\n')
  process.exit(1)
}

let pgn
try {
  pgn = readFileSync(filePath, 'utf8')
} catch (err) {
  process.stderr.write(`Cannot read file: ${err.message}\n`)
  process.exit(1)
}

const chess = new Chess()
try {
  chess.loadPgn(pgn.trim())
} catch (err) {
  process.stderr.write(`Invalid PGN: ${err instanceof Error ? err.message : String(err)}\n`)
  process.exit(1)
}

const rawHeaders = chess.getHeaders()
const verboseMoves = chess.history({ verbose: true })

if (verboseMoves.length === 0) {
  process.stderr.write('Invalid PGN: no moves found\n')
  process.exit(1)
}

const headers = {
  white: rawHeaders['White'] ?? 'Unknown',
  black: rawHeaders['Black'] ?? 'Unknown',
  result: mapResult(rawHeaders['Result'] ?? ''),
  played_at: parseDate(rawHeaders['Date']),
  eco_code: rawHeaders['ECO'] ?? '',
  opening_name: rawHeaders['Opening'] ?? 'Unknown',
}

const moves = verboseMoves.map((move, index) => ({
  move_number: index + 1,
  colour: move.color === 'w' ? 'white' : 'black',
  san: move.san,
  uci: move.lan,
  fen_before: move.before,
  fen_after: move.after,
}))

process.stdout.write(JSON.stringify({ headers, moves }))

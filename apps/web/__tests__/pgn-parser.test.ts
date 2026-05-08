import { readFileSync } from 'fs'
import { join } from 'path'
import { describe, it, expect } from 'vitest'
import { parsePgn, PgnParseError } from '../lib/pgn-parser'

const STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1'
const OPERA_GAME_TERMINAL_FEN = '1n1Rkb1r/p4ppp/4q3/4p1B1/4P3/8/PPP2PPP/2K5 b k - 1 17'
const UCI_PATTERN = /^[a-h][1-8][a-h][1-8][qrbn]?$/

const operaPgn = readFileSync(
  join(__dirname, 'fixtures/opera-game.pgn'),
  'utf8'
)

describe('parsePgn — Opera Game (Morphy vs Duke of Brunswick, 1858)', () => {
  const game = parsePgn(operaPgn)

  it('produces 33 half-moves', () => {
    expect(game.moveCount).toBe(33)
    expect(game.moves).toHaveLength(33)
  })

  it('first move is White e4 from the starting position', () => {
    const first = game.moves[0]
    expect(first.moveNumber).toBe(1)
    expect(first.colour).toBe('white')
    expect(first.san).toBe('e4')
    expect(first.uci).toBe('e2e4')
    expect(first.fenBefore).toBe(STARTING_FEN)
  })

  it('second move is Black and has moveNumber 2', () => {
    const second = game.moves[1]
    expect(second.moveNumber).toBe(2)
    expect(second.colour).toBe('black')
  })

  it('terminal position matches expected FEN after checkmate', () => {
    expect(game.moves[32].fenAfter).toBe(OPERA_GAME_TERMINAL_FEN)
  })

  it('all UCI strings are valid format', () => {
    for (const move of game.moves) {
      expect(move.uci).toMatch(UCI_PATTERN)
    }
  })

  it('move numbers are 1-indexed and sequential', () => {
    game.moves.forEach((move, i) => {
      expect(move.moveNumber).toBe(i + 1)
    })
  })

  it('odd move numbers are White, even are Black', () => {
    for (const move of game.moves) {
      if (move.moveNumber % 2 === 1) {
        expect(move.colour).toBe('white')
      } else {
        expect(move.colour).toBe('black')
      }
    }
  })

  it('each move fenAfter equals the next move fenBefore', () => {
    for (let i = 0; i < game.moves.length - 1; i++) {
      expect(game.moves[i].fenAfter).toBe(game.moves[i + 1].fenBefore)
    }
  })

  describe('headers', () => {
    it('extracts player names', () => {
      expect(game.headers.white).toBe('Paul Morphy')
      expect(game.headers.black).toBe('Duke of Brunswick')
    })

    it('maps result 1-0 to white', () => {
      expect(game.headers.result).toBe('white')
    })

    it('extracts ECO code', () => {
      expect(game.headers.ecoCode).toBe('C41')
    })

    it('extracts opening name', () => {
      expect(game.headers.openingName).toBe('Philidor Defense')
    })

    it('returns null playedAt when date contains ? placeholders', () => {
      expect(game.headers.playedAt).toBeNull()
    })

    it('exposes raw headers map', () => {
      expect(game.headers.raw['Event']).toBe('Paris')
    })
  })
})

describe('parsePgn — error handling', () => {
  it('throws PgnParseError for an empty string (no moves)', () => {
    expect(() => parsePgn('')).toThrow(PgnParseError)
  })

  it('throws PgnParseError for an illegal move sequence', () => {
    const badPgn = `[White "A"][Black "B"][Result "1-0"]\n1.e4 e5 2.Nf3 Nc6 3.Zz1 1-0`
    expect(() => parsePgn(badPgn)).toThrow(PgnParseError)
  })

  it('thrown error message starts with "Invalid PGN:"', () => {
    expect(() => parsePgn('garbage')).toThrow(/^Invalid PGN:/)
  })
})

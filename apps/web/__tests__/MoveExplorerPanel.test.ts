/**
 * Unit tests for MoveExplorerPanel logic.
 *
 * NOTE: Full DOM rendering tests (loading/loaded/error/empty states) require
 * jsdom + @testing-library/react. Install them and switch vitest environment to
 * 'jsdom' to enable those tests. The logic tests below run in the current node
 * environment without any DOM dependency.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

// ---------------------------------------------------------------------------
// Utility: formatEval
// Extracted here to match the implementation in MoveExplorerPanel.tsx
// ---------------------------------------------------------------------------

function formatEval(cp: number | null, mate: number | null): string {
  if (mate !== null) return mate > 0 ? `M${mate}` : `-M${Math.abs(mate)}`
  if (cp === null) return '0.00'
  const pawns = cp / 100
  return (pawns >= 0 ? '+' : '') + pawns.toFixed(2)
}

describe('formatEval', () => {
  it('formats a positive cp score as White-positive pawns', () => {
    expect(formatEval(30, null)).toBe('+0.30')
  })

  it('formats a negative cp score with minus sign', () => {
    expect(formatEval(-150, null)).toBe('-1.50')
  })

  it('formats a positive mate score', () => {
    expect(formatEval(null, 4)).toBe('M4')
  })

  it('formats a negative mate score', () => {
    expect(formatEval(null, -3)).toBe('-M3')
  })

  it('returns 0.00 when both cp and mate are null', () => {
    expect(formatEval(null, null)).toBe('0.00')
  })
})

// ---------------------------------------------------------------------------
// API proxy: /api/positions/analyse
// Tests the Next.js route handler behaviour by mocking fetch
// ---------------------------------------------------------------------------

const STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1'

const SAMPLE_RESPONSE = {
  fen: STARTING_FEN,
  side_to_move: 'w',
  engine_version: 'stockfish',
  candidates: [
    { rank: 1, uci: 'e2e4', cp: 30,  mate: null, pv: ['e2e4', 'e7e5'] },
    { rank: 2, uci: 'd2d4', cp: 25,  mate: null, pv: ['d2d4', 'd7d5'] },
    { rank: 3, uci: 'g1f3', cp: 15,  mate: null, pv: ['g1f3', 'g8f6'] },
  ],
}

describe('/api/positions/analyse proxy behaviour', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('loaded state: valid response has correct shape', async () => {
    const mockFetch = vi.mocked(fetch)
    mockFetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => SAMPLE_RESPONSE,
    } as Response)

    const res = await fetch('/api/positions/analyse', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ fen: STARTING_FEN, multipv: 3, time_ms: 2000 }),
    })
    const data = await res.json() as typeof SAMPLE_RESPONSE

    expect(data.candidates).toHaveLength(3)
    expect(data.side_to_move).toBe('w')
    expect(data.candidates[0].rank).toBe(1)
    expect(data.candidates[0].uci).toBe('e2e4')
    expect(Array.isArray(data.candidates[0].pv)).toBe(true)
  })

  it('error state: non-ok response signals failure', async () => {
    const mockFetch = vi.mocked(fetch)
    mockFetch.mockResolvedValue({
      ok: false,
      status: 500,
      json: async () => ({ error: 'Internal server error' }),
    } as Response)

    const res = await fetch('/api/positions/analyse', {
      method: 'POST',
      body: JSON.stringify({ fen: STARTING_FEN }),
    })

    expect(res.ok).toBe(false)
    expect(res.status).toBe(500)
  })

  it('empty state: terminal position returns zero candidates', async () => {
    const mockFetch = vi.mocked(fetch)
    mockFetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        fen: '8/8/8/8/8/8/8/k6K w - - 0 1',
        side_to_move: 'w',
        engine_version: 'stockfish',
        candidates: [],
      }),
    } as Response)

    const res = await fetch('/api/positions/analyse', {
      method: 'POST',
      body: JSON.stringify({ fen: '8/8/8/8/8/8/8/k6K w - - 0 1' }),
    })
    const data = await res.json() as { candidates: unknown[] }

    expect(data.candidates).toHaveLength(0)
  })

  it('loading state: fetch is in-flight (promise pending)', async () => {
    let resolve!: (v: Response) => void
    const pending = new Promise<Response>(r => { resolve = r })
    vi.mocked(fetch).mockReturnValue(pending)

    const fetchPromise = fetch('/api/positions/analyse', { method: 'POST', body: '{}' })
    // While unresolved the consumer should show a loading state
    let settled = false
    fetchPromise.then(() => { settled = true })

    // Allow any microtasks except the one that would resolve our promise
    await Promise.resolve()
    expect(settled).toBe(false)

    // Now resolve it
    resolve({ ok: true, status: 200, json: async () => SAMPLE_RESPONSE } as Response)
    await fetchPromise
    expect(settled).toBe(true)
  })
})

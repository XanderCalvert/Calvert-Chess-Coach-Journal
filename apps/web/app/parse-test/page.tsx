'use client'

import { useState, FormEvent } from 'react'
import type { ParsedGame } from '@/lib/pgn-parser'

interface ImportResult {
  game_id: string
  move_count: number
  parsed: Pick<ParsedGame, 'headers' | 'moves'>
}

interface ApiError {
  error: string
  detail?: unknown
}

export default function ParseTestPage() {
  const [pgn, setPgn] = useState('')
  const [result, setResult] = useState<ImportResult | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setLoading(true)
    setResult(null)
    setError(null)

    try {
      const res = await fetch('/api/import-pgn', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pgn }),
      })

      const data: ImportResult | ApiError = await res.json()

      if (!res.ok) {
        setError((data as ApiError).error ?? 'Unknown error')
      } else {
        setResult(data as ImportResult)
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Network error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="min-h-screen bg-[#1a1a2e] text-slate-200 p-8 font-[family-name:var(--font-dm-sans)]">
      <div className="max-w-5xl mx-auto space-y-8">
        <div>
          <h1 className="text-2xl font-semibold font-[family-name:var(--font-playfair)] text-white">
            PGN Parse Test
          </h1>
          <p className="mt-1 text-sm text-slate-400">
            Developer tool — paste a PGN to verify parsing and persistence end-to-end.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <textarea
            value={pgn}
            onChange={(e) => setPgn(e.target.value)}
            placeholder="Paste PGN here..."
            rows={10}
            className="w-full rounded-md bg-[#16213e] border border-slate-700 text-slate-200 p-3 font-[family-name:var(--font-dm-mono)] text-sm resize-y focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <button
            type="submit"
            disabled={loading || pgn.trim() === ''}
            className="px-5 py-2 rounded-md bg-blue-600 hover:bg-blue-500 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-medium transition-colors"
          >
            {loading ? 'Importing...' : 'Import PGN'}
          </button>
        </form>

        {error && (
          <div className="rounded-md border border-red-700 bg-red-950/40 p-4 text-sm text-red-300">
            {error}
          </div>
        )}

        {result && (
          <div className="space-y-6">
            <div className="rounded-md border border-green-700 bg-green-950/40 p-4 text-sm text-green-300">
              Persisted — game_id: <span className="font-[family-name:var(--font-dm-mono)]">{result.game_id}</span>
              {' '}| {result.move_count} half-moves stored
            </div>

            <section>
              <h2 className="text-lg font-semibold mb-3 text-slate-100">Headers</h2>
              <table className="w-full text-sm border-collapse">
                <tbody>
                  {Object.entries(result.parsed.headers.raw).map(([key, value]) => (
                    <tr key={key} className="border-b border-slate-700/50">
                      <td className="py-1.5 pr-4 text-slate-400 w-36">{key}</td>
                      <td className="py-1.5 font-[family-name:var(--font-dm-mono)]">{value}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </section>

            <section>
              <h2 className="text-lg font-semibold mb-3 text-slate-100">
                Moves ({result.parsed.moves.length})
              </h2>
              <div className="overflow-x-auto rounded-md border border-slate-700">
                <table className="w-full text-sm">
                  <thead className="bg-slate-800 text-slate-400 text-xs uppercase tracking-wider">
                    <tr>
                      {['#', 'Colour', 'SAN', 'UCI', 'FEN Before', 'FEN After'].map((h) => (
                        <th key={h} className="px-3 py-2 text-left font-medium">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {result.parsed.moves.map((move) => (
                      <tr
                        key={move.moveNumber}
                        className="border-t border-slate-700/50 hover:bg-slate-800/40"
                      >
                        <td className="px-3 py-1.5 text-slate-400">{move.moveNumber}</td>
                        <td className="px-3 py-1.5">
                          <span
                            className={
                              move.colour === 'white'
                                ? 'text-slate-100'
                                : 'text-slate-500'
                            }
                          >
                            {move.colour}
                          </span>
                        </td>
                        <td className="px-3 py-1.5 font-[family-name:var(--font-dm-mono)] text-yellow-300">
                          {move.san}
                        </td>
                        <td className="px-3 py-1.5 font-[family-name:var(--font-dm-mono)] text-blue-300">
                          {move.uci}
                        </td>
                        <td className="px-3 py-1.5 font-[family-name:var(--font-dm-mono)] text-slate-400 text-xs max-w-xs truncate">
                          {move.fenBefore}
                        </td>
                        <td className="px-3 py-1.5 font-[family-name:var(--font-dm-mono)] text-slate-400 text-xs max-w-xs truncate">
                          {move.fenAfter}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          </div>
        )}
      </div>
    </main>
  )
}

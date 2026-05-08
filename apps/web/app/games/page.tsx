'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import Nav from '@/components/Nav'

interface GameSummary {
  id: string
  share_code: string | null
  white_player: string
  black_player: string
  result: string
  played_at: string | null
  eco_code: string
  opening_name: string
  move_count: number
  analysis_status: 'pending' | 'running' | 'complete' | 'failed'
  accuracy_pct: string | null
  blunder_count: number | null
  mistake_count: number | null
  inaccuracy_count: number | null
}

const RESULT_LABEL: Record<string, string> = {
  white: '1-0',
  black: '0-1',
  draw:  '½-½',
  unknown: '—',
}

const STATUS_STYLES: Record<string, { label: string; color: string }> = {
  complete: { label: 'Complete',   color: '#4ade80' },
  running:  { label: 'Analysing…', color: 'var(--gold)' },
  pending:  { label: 'Queued',     color: 'var(--text-muted)' },
  failed:   { label: 'Failed',     color: '#f87171' },
}

const PAGE_SIZE = 10

export default function GamesPage() {
  const [games, setGames] = useState<GameSummary[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [page, setPage] = useState(1)

  useEffect(() => {
    async function fetchGames() {
      setLoading(true)
      setError(null)
      try {
        const res = await fetch('/api/games')
        if (!res.ok) {
          const d = await res.json().catch(() => null)
          setError(d?.error ?? `Failed to load games (${res.status})`)
          return
        }
        setGames(await res.json())
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Network error')
      } finally {
        setLoading(false)
      }
    }
    fetchGames()
  }, [])

  const totalPages = Math.max(1, Math.ceil(games.length / PAGE_SIZE))
  const pageGames = games.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

  return (
    <>
      <Nav />
      <main className="flex-1 px-6 py-12 max-w-5xl mx-auto w-full">
        <div className="mb-8 flex items-center justify-between">
          <div>
            <h1
              className="text-3xl font-semibold mb-1"
              style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}
            >
              Latest games analysed
            </h1>
            {!loading && !error && (
              <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                {games.length} {games.length === 1 ? 'game' : 'games'} imported
              </p>
            )}
          </div>
          <Link
            href="/import"
            className="px-5 py-2 rounded text-sm font-medium"
            style={{ background: 'var(--gold)', color: 'var(--bg)' }}
          >
            Import game
          </Link>
        </div>

        {loading && (
          <p style={{ color: 'var(--text-muted)' }}>Loading…</p>
        )}

        {error && (
          <div className="p-4 rounded text-sm" style={{ background: 'rgba(220,60,60,0.1)', border: '1px solid rgba(220,60,60,0.3)', color: 'var(--red)' }}>
            {error}
          </div>
        )}

        {!loading && !error && games.length === 0 && (
          <div className="p-8 rounded text-center" style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}>
            <p className="mb-3" style={{ color: 'var(--text-muted)' }}>No games imported yet.</p>
            <Link href="/import" style={{ color: 'var(--gold)' }} className="text-sm">
              Import your first game →
            </Link>
          </div>
        )}

        {pageGames.length > 0 && (
          <>
            <div className="rounded overflow-hidden mb-6" style={{ border: '1px solid rgba(232,224,208,0.10)' }}>
              <table className="w-full text-sm" style={{ borderCollapse: 'collapse' }}>
                <thead>
                  <tr style={{ background: 'var(--surface)', borderBottom: '1px solid rgba(232,224,208,0.10)' }}>
                    {['Players', 'Opening', 'Date', 'Moves', 'Accuracy', 'Status', ''].map(h => (
                      <th
                        key={h}
                        className="text-left px-4 py-3 text-xs uppercase tracking-wider"
                        style={{ color: 'var(--text-muted)', whiteSpace: 'nowrap' }}
                      >
                        {h}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {pageGames.map((g, i) => {
                    const status = STATUS_STYLES[g.analysis_status] ?? STATUS_STYLES.pending
                    return (
                      <tr
                        key={g.id}
                        style={{
                          borderBottom: i < pageGames.length - 1 ? '1px solid rgba(232,224,208,0.06)' : undefined,
                        }}
                      >
                        <td className="px-4 py-3">
                          <span style={{ color: 'var(--text)' }}>{g.white_player}</span>
                          <span className="mx-1.5" style={{ color: 'var(--text-faint)' }}>
                            {RESULT_LABEL[g.result] ?? '—'}
                          </span>
                          <span style={{ color: 'var(--text)' }}>{g.black_player}</span>
                        </td>
                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)', maxWidth: '180px' }}>
                          <span className="block truncate">
                            {g.opening_name || '—'}
                            {g.eco_code ? <span className="ml-1.5" style={{ color: 'var(--text-faint)', fontFamily: 'var(--font-dm-mono)', fontSize: '11px' }}>{g.eco_code}</span> : null}
                          </span>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap" style={{ color: 'var(--text-muted)', fontFamily: 'var(--font-dm-mono)', fontSize: '12px' }}>
                          {g.played_at ?? '—'}
                        </td>
                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>
                          {g.move_count}
                        </td>
                        <td className="px-4 py-3 font-medium" style={{ color: 'var(--gold)', fontFamily: 'var(--font-dm-mono)' }}>
                          {g.accuracy_pct != null ? `${g.accuracy_pct}%` : '—'}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <span className="text-xs" style={{ color: status.color }}>{status.label}</span>
                        </td>
                        <td className="px-4 py-3 text-right">
                          <Link
                            href={g.share_code ? `/g/${g.share_code}` : `/games/${g.id}/analysis`}
                            className="text-xs px-3 py-1 rounded"
                            style={{ color: 'var(--gold)', border: '1px solid rgba(201,168,76,0.3)' }}
                          >
                            View →
                          </Link>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            {totalPages > 1 && (
              <div className="flex items-center justify-between text-sm">
                <span style={{ color: 'var(--text-muted)' }}>
                  Page {page} of {totalPages}
                </span>
                <div className="flex gap-2">
                  <button
                    onClick={() => setPage(p => Math.max(1, p - 1))}
                    disabled={page === 1}
                    className="px-4 py-1.5 rounded"
                    style={{
                      background: 'var(--surface)',
                      color: page === 1 ? 'var(--text-faint)' : 'var(--text)',
                      border: '1px solid rgba(232,224,208,0.12)',
                      cursor: page === 1 ? 'not-allowed' : 'pointer',
                    }}
                  >
                    ← Prev
                  </button>
                  <button
                    onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                    disabled={page === totalPages}
                    className="px-4 py-1.5 rounded"
                    style={{
                      background: 'var(--surface)',
                      color: page === totalPages ? 'var(--text-faint)' : 'var(--text)',
                      border: '1px solid rgba(232,224,208,0.12)',
                      cursor: page === totalPages ? 'not-allowed' : 'pointer',
                    }}
                  >
                    Next →
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </main>
    </>
  )
}

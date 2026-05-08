'use client'

import { useEffect, useState } from 'react'
import { useParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import Nav from '@/components/Nav'

interface ConnectedAccount {
  id: string
  platform: string
  username: string
  normalised_username: string
  rapid_rating: number | null
  blitz_rating: number | null
  bullet_rating: number | null
  daily_rating: number | null
  last_synced_at: string | null
  sync_status: string
}

interface ProfileGame {
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
  time_control: string | null
  opponent_username: string | null
  opponent_rating: number | null
  user_rating_before: number | null
}

interface GamesMeta {
  current_page: number
  last_page: number
  total: number
}

const RESULT_LABEL: Record<string, string> = {
  white: '1-0',
  black: '0-1',
  draw: '½-½',
  unknown: '—',
}

const STATUS_STYLES: Record<string, { label: string; color: string }> = {
  complete: { label: 'Complete',   color: '#4ade80' },
  running:  { label: 'Analysing…', color: 'var(--gold)' },
  pending:  { label: 'Queued',     color: 'var(--text-muted)' },
  failed:   { label: 'Failed',     color: '#f87171' },
}

function RatingStat({ label, value }: { label: string; value: number | null }) {
  return (
    <div className="text-center px-5 py-4 rounded" style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}>
      <div className="text-xs uppercase tracking-wider mb-1" style={{ color: 'var(--text-muted)' }}>{label}</div>
      <div className="text-2xl font-semibold" style={{ fontFamily: 'var(--font-dm-mono)', color: value != null ? 'var(--text)' : 'var(--text-faint)' }}>
        {value ?? '—'}
      </div>
    </div>
  )
}

function TrendStat({ label, value }: { label: string; value: string }) {
  return (
    <div className="px-4 py-3 rounded" style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}>
      <div className="text-xs uppercase tracking-wider mb-0.5" style={{ color: 'var(--text-muted)' }}>{label}</div>
      <div className="text-lg font-medium" style={{ fontFamily: 'var(--font-dm-mono)', color: 'var(--gold)' }}>{value}</div>
    </div>
  )
}

function computeTrends(games: ProfileGame[]) {
  const analysed = games.filter(g => g.analysis_status === 'complete')
  if (analysed.length === 0) return { avgCpl: null, avgBlunders: null }

  const withAccuracy = analysed.filter(g => g.accuracy_pct != null)
  const withBlunders = analysed.filter(g => g.blunder_count != null)

  // accuracy → avg CPL approximation is unavailable client-side; show "—" and rely on Phase 3
  const avgBlunders = withBlunders.length > 0
    ? (withBlunders.reduce((s, g) => s + (g.blunder_count ?? 0), 0) / withBlunders.length).toFixed(1)
    : null

  const avgAccuracy = withAccuracy.length > 0
    ? (withAccuracy.reduce((s, g) => s + parseFloat(g.accuracy_pct ?? '0'), 0) / withAccuracy.length).toFixed(1)
    : null

  return { avgCpl: avgAccuracy, avgBlunders }
}

export default function ProfilePage() {
  const { username } = useParams<{ username: string }>()
  const router = useRouter()

  const [account, setAccount] = useState<ConnectedAccount | null>(null)
  const [notFound, setNotFound] = useState(false)
  const [games, setGames] = useState<ProfileGame[]>([])
  const [meta, setMeta] = useState<GamesMeta | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [gamesLoading, setGamesLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Form state (shown when notFound)
  const [formUsername, setFormUsername] = useState(username ?? '')
  const [formSubmitting, setFormSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  useEffect(() => {
    if (!username) return
    setLoading(true)
    setNotFound(false)
    setError(null)

    fetch(`/api/connected-accounts/chesscom/${username}`)
      .then(async res => {
        if (res.status === 404) { setNotFound(true); return }
        if (!res.ok) {
          const d = await res.json().catch(() => null)
          setError(d?.error ?? `Error loading profile (${res.status})`)
          return
        }
        setAccount(await res.json())
      })
      .catch(err => setError(err instanceof Error ? err.message : 'Network error'))
      .finally(() => setLoading(false))
  }, [username])

  useEffect(() => {
    if (!account) return
    setGamesLoading(true)

    fetch(`/api/connected-accounts/chesscom/${account.normalised_username ?? username}/games?page=${page}`)
      .then(async res => {
        if (!res.ok) return
        const d = await res.json()
        setGames(d.data ?? [])
        setMeta(d.meta ?? null)
      })
      .finally(() => setGamesLoading(false))
  }, [account, page, username])

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault()
    setFormSubmitting(true)
    setFormError(null)

    try {
      const res = await fetch('/api/connected-accounts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ platform: 'chesscom', username: formUsername.trim() }),
      })
      const d = await res.json()
      if (!res.ok) {
        setFormError(d?.message ?? `Could not create profile (${res.status})`)
        return
      }
      router.push(`/u/${d.normalised_username}`)
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'Network error')
    } finally {
      setFormSubmitting(false)
    }
  }

  const { avgCpl, avgBlunders } = computeTrends(games)
  const lastSynced = account?.last_synced_at
    ? new Date(account.last_synced_at).toLocaleString()
    : 'Never'

  if (loading) {
    return (
      <>
        <Nav />
        <main className="flex-1 px-6 py-12 max-w-5xl mx-auto w-full">
          <p style={{ color: 'var(--text-muted)' }}>Loading…</p>
        </main>
      </>
    )
  }

  if (error) {
    return (
      <>
        <Nav />
        <main className="flex-1 px-6 py-12 max-w-5xl mx-auto w-full">
          <div className="p-4 rounded text-sm" style={{ background: 'rgba(220,60,60,0.1)', border: '1px solid rgba(220,60,60,0.3)', color: '#f87171' }}>
            {error}
          </div>
        </main>
      </>
    )
  }

  if (notFound) {
    return (
      <>
        <Nav />
        <main className="flex-1 px-6 py-12 max-w-5xl mx-auto w-full">
          <div className="max-w-md mx-auto mt-12 p-8 rounded" style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}>
            <h1 className="text-xl font-semibold mb-2" style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}>
              No profile found
            </h1>
            <p className="text-sm mb-6" style={{ color: 'var(--text-muted)' }}>
              Track a Chess.com account to see games and trends.
            </p>
            <form onSubmit={handleCreate} className="flex flex-col gap-3">
              <input
                type="text"
                placeholder="Chess.com username"
                value={formUsername}
                onChange={e => setFormUsername(e.target.value)}
                required
                className="w-full px-4 py-2 rounded text-sm"
                style={{ background: 'var(--bg)', border: '1px solid rgba(232,224,208,0.20)', color: 'var(--text)', outline: 'none' }}
              />
              {formError && (
                <p className="text-xs" style={{ color: '#f87171' }}>{formError}</p>
              )}
              <button
                type="submit"
                disabled={formSubmitting || !formUsername.trim()}
                className="px-5 py-2 rounded text-sm font-medium"
                style={{ background: 'var(--gold)', color: 'var(--bg)', opacity: formSubmitting ? 0.6 : 1, cursor: formSubmitting ? 'not-allowed' : 'pointer' }}
              >
                {formSubmitting ? 'Creating…' : 'Create profile'}
              </button>
            </form>
          </div>
        </main>
      </>
    )
  }

  if (!account) return null

  return (
    <>
      <Nav />
      <main className="flex-1 px-6 py-12 max-w-5xl mx-auto w-full">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-semibold mb-1" style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}>
            {account.username}
          </h1>
          <p className="text-sm" style={{ color: 'var(--text-muted)' }}>Chess.com</p>
        </div>

        {/* Rating stats */}
        <div className="grid grid-cols-4 gap-3 mb-6">
          <RatingStat label="Rapid"  value={account.rapid_rating} />
          <RatingStat label="Blitz"  value={account.blitz_rating} />
          <RatingStat label="Bullet" value={account.bullet_rating} />
          <RatingStat label="Daily"  value={account.daily_rating} />
        </div>

        {/* Meta row */}
        <div className="flex gap-6 mb-6 text-sm" style={{ color: 'var(--text-muted)' }}>
          <span><strong style={{ color: 'var(--text)' }}>{meta?.total ?? 0}</strong> games imported</span>
          <span>Last synced: <strong style={{ color: 'var(--text)' }}>{lastSynced}</strong></span>
        </div>

        {/* Trend cards */}
        <div className="grid grid-cols-2 gap-3 mb-8 max-w-xs">
          <TrendStat label="Avg accuracy" value={avgCpl != null ? `${avgCpl}%` : '—'} />
          <TrendStat label="Blunders/game" value={avgBlunders ?? '—'} />
        </div>

        {/* Games table */}
        {gamesLoading ? (
          <p style={{ color: 'var(--text-muted)' }}>Loading games…</p>
        ) : games.length === 0 ? (
          <div className="p-8 rounded text-center" style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}>
            <p style={{ color: 'var(--text-muted)' }}>No games imported yet for this account.</p>
          </div>
        ) : (
          <>
            <div className="rounded overflow-hidden mb-6" style={{ border: '1px solid rgba(232,224,208,0.10)' }}>
              <table className="w-full text-sm" style={{ borderCollapse: 'collapse' }}>
                <thead>
                  <tr style={{ background: 'var(--surface)', borderBottom: '1px solid rgba(232,224,208,0.10)' }}>
                    {['Result', 'Opponent', 'Time control', 'Accuracy', 'Status', ''].map(h => (
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
                  {games.map((g, i) => {
                    const status = STATUS_STYLES[g.analysis_status] ?? STATUS_STYLES.pending
                    return (
                      <tr
                        key={g.id}
                        style={{ borderBottom: i < games.length - 1 ? '1px solid rgba(232,224,208,0.06)' : undefined }}
                      >
                        <td className="px-4 py-3 font-medium" style={{ color: 'var(--text)', fontFamily: 'var(--font-dm-mono)' }}>
                          {RESULT_LABEL[g.result] ?? '—'}
                        </td>
                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>
                          {g.opponent_username ?? '—'}
                          {g.opponent_rating != null && (
                            <span className="ml-1.5" style={{ color: 'var(--text-faint)', fontSize: '11px', fontFamily: 'var(--font-dm-mono)' }}>
                              ({g.opponent_rating})
                            </span>
                          )}
                        </td>
                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)', fontFamily: 'var(--font-dm-mono)', fontSize: '12px' }}>
                          {g.time_control ?? '—'}
                        </td>
                        <td className="px-4 py-3 font-medium" style={{ color: 'var(--gold)', fontFamily: 'var(--font-dm-mono)' }}>
                          {g.accuracy_pct != null ? `${g.accuracy_pct}%` : '—'}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">
                          <span className="text-xs" style={{ color: status.color }}>{status.label}</span>
                        </td>
                        <td className="px-4 py-3 text-right">
                          {g.share_code && (
                            <Link
                              href={`/g/${g.share_code}`}
                              className="text-xs px-3 py-1 rounded"
                              style={{ color: 'var(--gold)', border: '1px solid rgba(201,168,76,0.3)' }}
                            >
                              View →
                            </Link>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            {meta && meta.last_page > 1 && (
              <div className="flex items-center justify-between text-sm">
                <span style={{ color: 'var(--text-muted)' }}>
                  Page {meta.current_page} of {meta.last_page}
                </span>
                <div className="flex gap-2">
                  <button
                    onClick={() => setPage(p => Math.max(1, p - 1))}
                    disabled={meta.current_page === 1}
                    className="px-4 py-1.5 rounded"
                    style={{
                      background: 'var(--surface)',
                      color: meta.current_page === 1 ? 'var(--text-faint)' : 'var(--text)',
                      border: '1px solid rgba(232,224,208,0.12)',
                      cursor: meta.current_page === 1 ? 'not-allowed' : 'pointer',
                    }}
                  >
                    ← Prev
                  </button>
                  <button
                    onClick={() => setPage(p => Math.min(meta.last_page, p + 1))}
                    disabled={meta.current_page === meta.last_page}
                    className="px-4 py-1.5 rounded"
                    style={{
                      background: 'var(--surface)',
                      color: meta.current_page === meta.last_page ? 'var(--text-faint)' : 'var(--text)',
                      border: '1px solid rgba(232,224,208,0.12)',
                      cursor: meta.current_page === meta.last_page ? 'not-allowed' : 'pointer',
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

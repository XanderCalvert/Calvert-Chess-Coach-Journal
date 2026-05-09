'use client'

import { useEffect, useMemo, useState } from 'react'
import { useParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import Nav from '@/components/Nav'
import Sparkline from '@/components/Sparkline'

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

type GameTypeFilter = 'all' | 'bullet' | 'blitz' | 'rapid' | 'daily'

type TimeframeDays = 0 | 30 | 90 | 180 | 365

const TIMEFRAME_OPTIONS: Array<{ value: TimeframeDays; label: string }> = [
  { value: 30, label: '30 days' },
  { value: 90, label: '90 days' },
  { value: 180, label: '180 days' },
  { value: 365, label: '365 days' },
  { value: 0, label: 'All time' },
]

interface AnalysedCountsByType {
  bullet: number
  blitz: number
  rapid: number
  daily: number
}

interface TrendPoint { played_at: string; rating?: number; avg_cp_loss?: number; blunders?: number }

interface RecentGame {
  share_code: string
  played_at: string | null
  result: 'WIN' | 'LOSS' | 'DRAW'
  opponent_username: string | null
  avg_cp_loss: number | null
  blunder_count: number
}

interface ProfileStats {
  games_analysed: number
  wins: number
  draws: number
  losses: number
  avg_cp_loss: number | null
  blunders_per_game: number | null
  mistakes_per_game: number | null
  inaccuracies_per_game: number | null
  rating_trend: TrendPoint[]
  cp_loss_trend: TrendPoint[]
  blunders_trend: TrendPoint[]
  recent_games: RecentGame[]
  analysed_counts_by_type?: AnalysedCountsByType
  recommended_game_type?: string | null
}

const RESULT_LABEL: Record<string, string> = {
  white: '1-0',
  black: '0-1',
  draw: '½-½',
  unknown: '—',
}

const PLAYER_RESULT_COLOUR: Record<string, string> = {
  WIN:  'var(--green)',
  LOSS: 'var(--red)',
  DRAW: 'var(--text-muted)',
}

const STATUS_STYLES: Record<string, { label: string; color: string }> = {
  complete: { label: 'Complete',   color: '#4ade80' },
  running:  { label: 'Analysing…', color: 'var(--gold)' },
  pending:  { label: 'Queued',     color: 'var(--text-muted)' },
  failed:   { label: 'Failed',     color: '#f87171' },
}

const SYNC_STATUS_STYLES: Record<string, { label: string; color: string }> = {
  never_synced: { label: 'Never synced', color: 'var(--text-faint)' },
  syncing:      { label: 'Syncing…',     color: 'var(--gold)' },
  synced:       { label: 'Synced',       color: '#4ade80' },
  failed:       { label: 'Sync failed',  color: '#f87171' },
}

const GAME_TYPE_OPTIONS: Array<{ value: GameTypeFilter; label: string }> = [
  { value: 'all', label: 'All types' },
  { value: 'bullet', label: 'Bullet' },
  { value: 'blitz', label: 'Blitz' },
  { value: 'rapid', label: 'Rapid' },
  { value: 'daily', label: 'Daily' },
]

const STAT_HELP: Record<string, string> = {
  analysed: 'Number of games with completed analysis in the selected game type and timeframe.',
  wdl: 'Wins, draws, and losses from your perspective in analysed games (within the timeframe).',
  avgCpl: 'Average centipawn loss on your moves only. Lower is better.',
  blunders: 'Average number of blunders per analysed game.',
  mistakes: 'Average number of mistakes per analysed game.',
  inaccuracies: 'Average number of inaccuracies per analysed game.',
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

function StatCard({ label, value, color, helpText }: { label: string; value: string; color?: string; helpText?: string }) {
  return (
    <div className="px-4 py-3 rounded" style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}>
      <div className="text-xs uppercase tracking-wider mb-0.5" style={{ color: 'var(--text-muted)' }}>
        <StatLabel label={label} helpText={helpText} />
      </div>
      <div className="text-lg font-medium" style={{ fontFamily: 'var(--font-dm-mono)', color: color ?? 'var(--gold)' }}>{value}</div>
    </div>
  )
}

function StatLabel({ label, helpText }: { label: string; helpText?: string }) {
  return (
    <div className="flex items-center gap-1">
      <span>{label}</span>
      {helpText && (
        <span
          title={helpText}
          aria-label={helpText}
          className="inline-flex items-center justify-center rounded-full text-[10px] h-4 w-4"
          style={{ border: '1px solid rgba(232,224,208,0.25)', color: 'var(--text-faint)' }}
        >
          ?
        </span>
      )}
    </div>
  )
}

function StatCardSkeleton() {
  return (
    <div className="px-4 py-3 rounded" style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}>
      <div className="h-2.5 w-20 rounded mb-2" style={{ background: 'rgba(232,224,208,0.08)' }} />
      <div className="h-5 w-12 rounded" style={{ background: 'rgba(232,224,208,0.08)' }} />
    </div>
  )
}

function SparklinePanel({ title, subtitle, data, minValue, maxValue }: {
  title: string
  subtitle: string
  data: number[]
  minValue: number
  maxValue: number
}) {
  return (
    <div className="p-4 rounded" style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}>
      <div className="text-xs uppercase tracking-wider mb-0.5" style={{ color: 'var(--text-muted)' }}>{title}</div>
      <div className="text-sm font-medium mb-3" style={{ fontFamily: 'var(--font-dm-mono)', color: 'var(--gold)' }}>{subtitle}</div>
      {data.length >= 2 ? (
        <Sparkline data={data} minValue={minValue} maxValue={maxValue} />
      ) : (
        <div className="h-[60px] flex items-center justify-center text-xs" style={{ color: 'var(--text-faint)' }}>
          Not enough data
        </div>
      )}
    </div>
  )
}

function fmt(n: number | null, decimals = 1): string {
  return n != null ? n.toFixed(decimals) : '—'
}

function isGameTypeFilter(value: string | null): value is Exclude<GameTypeFilter, 'all'> {
  return value === 'bullet' || value === 'blitz' || value === 'rapid' || value === 'daily'
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
  const [gamesTrigger, setGamesTrigger] = useState(0)

  const [stats, setStats] = useState<ProfileStats | null>(null)
  const [statsLoading, setStatsLoading] = useState(true)
  /** `null` until first bootstrap fetch picks default from API `recommended_game_type`. */
  const [gameTypeFilter, setGameTypeFilter] = useState<GameTypeFilter | null>(null)
  const [timeframeDays, setTimeframeDays] = useState<TimeframeDays>(90)
  const [analysedCountsByType, setAnalysedCountsByType] = useState<AnalysedCountsByType | null>(null)

  // Sync state
  const [syncing, setSyncing] = useState(false)
  const [syncError, setSyncError] = useState<string | null>(null)

  // Form state (shown when notFound)
  const [formUsername, setFormUsername] = useState(username ?? '')
  const [formSubmitting, setFormSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  useEffect(() => {
    if (!username) return
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setLoading(true)
    setNotFound(false)
    setError(null)
    setGameTypeFilter(null)
    setTimeframeDays(90)
    setAnalysedCountsByType(null)
    setStats(null)

    fetch(`/api/connected-accounts/chesscom/${username}`).then(async accountRes => {
      if (accountRes.status === 404) { setNotFound(true); return }
      if (!accountRes.ok) {
        const d = await accountRes.json().catch(() => null)
        setError(d?.error ?? `Error loading profile (${accountRes.status})`)
        return
      }
      setAccount(await accountRes.json())
    }).catch(err => setError(err instanceof Error ? err.message : 'Network error'))
      .finally(() => setLoading(false))
  }, [username])

  // Bootstrap default game-type filter from analysed counts (one fetch with `all`).
  useEffect(() => {
    if (!username || notFound) return
    if (gameTypeFilter !== null) return

    fetch(`/api/connected-accounts/chesscom/${username}/stats?game_type=all`)
      .then(async res => {
        if (!res.ok) {
          setGameTypeFilter('all')
          return
        }
        const data: ProfileStats = await res.json()
        if (data.analysed_counts_by_type) {
          setAnalysedCountsByType(data.analysed_counts_by_type)
        }
        const rec = data.recommended_game_type
        setGameTypeFilter(isGameTypeFilter(rec) ? rec : 'all')
      })
      .catch(() => setGameTypeFilter('all'))
  }, [username, notFound, gameTypeFilter])

  useEffect(() => {
    if (!username || notFound) return
    if (gameTypeFilter === null) return
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setStatsLoading(true)
    fetch(`/api/connected-accounts/chesscom/${username}/stats?game_type=${gameTypeFilter}&days=${timeframeDays}`)
      .then(async res => {
        if (!res.ok) return
        const data: ProfileStats = await res.json()
        setStats(data)
        if (data.analysed_counts_by_type) {
          setAnalysedCountsByType(data.analysed_counts_by_type)
        }
      })
      .finally(() => setStatsLoading(false))
  }, [username, gameTypeFilter, timeframeDays, notFound])

  // If the selected type has no analysed games (e.g. after sync), fall back.
  useEffect(() => {
    if (gameTypeFilter === null || !analysedCountsByType) return
    if (gameTypeFilter === 'all') return
    if (analysedCountsByType[gameTypeFilter] > 0) return
    const rec = stats?.recommended_game_type
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setGameTypeFilter(isGameTypeFilter(rec) ? rec : 'all')
  }, [analysedCountsByType, gameTypeFilter, stats?.recommended_game_type])

  // Refresh stats after sync completes
  async function refreshStats() {
    if (gameTypeFilter === null) return
    const res = await fetch(`/api/connected-accounts/chesscom/${username}/stats?game_type=${gameTypeFilter}&days=${timeframeDays}`)
    if (res.ok) {
      const data: ProfileStats = await res.json()
      setStats(data)
      if (data.analysed_counts_by_type) {
        setAnalysedCountsByType(data.analysed_counts_by_type)
      }
    }
  }

  // Poll sync status while syncing
  useEffect(() => {
    if (account?.sync_status !== 'syncing') return

    const id = setInterval(async () => {
      try {
        const res = await fetch(`/api/connected-accounts/chesscom/${username}`)
        if (!res.ok) return
        const updated: ConnectedAccount = await res.json()
        setAccount(updated)
        if (updated.sync_status === 'synced' || updated.sync_status === 'failed') {
          clearInterval(id)
          setGamesTrigger(n => n + 1)
          refreshStats()
        }
      } catch {
        // ignore transient polling errors
      }
    }, 3000)

    return () => clearInterval(id)
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [account?.sync_status, username])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setPage(1)
  }, [gameTypeFilter])

  useEffect(() => {
    if (!account) return
    if (gameTypeFilter === null) return
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setGamesLoading(true)

    fetch(`/api/connected-accounts/chesscom/${account.normalised_username ?? username}/games?page=${page}&game_type=${gameTypeFilter}`)
      .then(async res => {
        if (!res.ok) return
        const d = await res.json()
        setGames(d.data ?? [])
        setMeta(d.meta ?? null)
      })
      .finally(() => setGamesLoading(false))
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [account?.id, page, gamesTrigger, gameTypeFilter, username])

  const gameTypeDropdownOptions = useMemo(() => {
    if (!analysedCountsByType) {
      return GAME_TYPE_OPTIONS
    }
    return [
      GAME_TYPE_OPTIONS[0],
      ...GAME_TYPE_OPTIONS.slice(1).filter(
        option => analysedCountsByType[option.value as keyof AnalysedCountsByType] > 0
      ),
    ]
  }, [analysedCountsByType])

  async function handleSync() {
    setSyncing(true)
    setSyncError(null)
    try {
      const res = await fetch(`/api/connected-accounts/chesscom/${username}/sync`, { method: 'POST' })
      const data = await res.json()
      if (!res.ok && res.status !== 409) {
        setSyncError(data?.error ?? data?.message ?? `Sync failed (${res.status})`)
        return
      }
      setAccount(data)
    } catch (err) {
      setSyncError(err instanceof Error ? err.message : 'Network error')
    } finally {
      setSyncing(false)
    }
  }

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

  const lastSynced = account?.last_synced_at
    ? new Date(account.last_synced_at).toLocaleString()
    : null

  const syncStatusStyle = account
    ? (SYNC_STATUS_STYLES[account.sync_status] ?? SYNC_STATUS_STYLES.never_synced)
    : null

  const isSyncing = account?.sync_status === 'syncing' || syncing

  // Sparkline data arrays
  const ratingData = stats?.rating_trend.map(p => p.rating!).filter(v => v != null) ?? []
  const cpLossData  = stats?.cp_loss_trend.map(p => p.avg_cp_loss!) ?? []
  const blunderData = stats?.blunders_trend.map(p => p.blunders!) ?? []

  const ratingMin = ratingData.length ? Math.max(0, Math.min(...ratingData) - 50) : 800
  const ratingMax = ratingData.length ? Math.max(...ratingData) + 50 : 1200
  const cpLossMax  = cpLossData.length  ? Math.max(Math.max(...cpLossData) + 20, 100) : 100
  const blunderMax = blunderData.length ? Math.max(Math.max(...blunderData) + 1, 5) : 5

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
        <div className="flex items-start justify-between mb-8">
          <div>
            <h1 className="text-3xl font-semibold mb-1" style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}>
              {account.username}
            </h1>
            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>Chess.com</p>
          </div>
          <button
            onClick={handleSync}
            disabled={isSyncing}
            className="px-5 py-2 rounded text-sm font-medium"
            style={{
              background: 'var(--gold)',
              color: 'var(--bg)',
              opacity: isSyncing ? 0.6 : 1,
              cursor: isSyncing ? 'not-allowed' : 'pointer',
            }}
          >
            {isSyncing ? 'Syncing…' : 'Sync Now'}
          </button>
        </div>

        {syncError && (
          <div className="mb-4 p-3 rounded text-sm" style={{ background: 'rgba(220,60,60,0.1)', border: '1px solid rgba(220,60,60,0.3)', color: '#f87171' }}>
            {syncError}
          </div>
        )}

        {/* Rating stats */}
        <div className="grid grid-cols-4 gap-3 mb-6">
          <RatingStat label="Rapid"  value={account.rapid_rating} />
          <RatingStat label="Blitz"  value={account.blitz_rating} />
          <RatingStat label="Bullet" value={account.bullet_rating} />
          <RatingStat label="Daily"  value={account.daily_rating} />
        </div>

        {/* Meta row */}
        <div className="flex flex-wrap gap-x-6 gap-y-1 mb-8 text-sm" style={{ color: 'var(--text-muted)' }}>
          <span><strong style={{ color: 'var(--text)' }}>{meta?.total ?? 0}</strong> games imported</span>
          {lastSynced && (
            <span>Last synced: <strong style={{ color: 'var(--text)' }}>{lastSynced}</strong></span>
          )}
          {syncStatusStyle && (
            <span style={{ color: syncStatusStyle.color }}>{syncStatusStyle.label}</span>
          )}
        </div>

        {/* Trend dashboard */}
        <section className="mb-10">
          <div className="flex flex-wrap items-end justify-between gap-3 mb-4">
            <h2 className="text-base font-semibold" style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}>
              Analysis trends
            </h2>
            <div className="flex flex-wrap items-end gap-4">
              <label className="flex items-center gap-2 text-xs uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                Timeframe
                <select
                  value={timeframeDays}
                  disabled={gameTypeFilter === null}
                  onChange={e => setTimeframeDays(Number(e.target.value) as TimeframeDays)}
                  className="px-3 py-1.5 rounded text-xs normal-case tracking-normal"
                  style={{
                    background: 'var(--surface)',
                    color: 'var(--text)',
                    border: '1px solid rgba(232,224,208,0.18)',
                    opacity: gameTypeFilter === null ? 0.65 : 1,
                  }}
                >
                  {TIMEFRAME_OPTIONS.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="flex items-center gap-2 text-xs uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                Game type
                <select
                  value={gameTypeFilter ?? 'all'}
                  disabled={gameTypeFilter === null}
                  onChange={e => setGameTypeFilter(e.target.value as GameTypeFilter)}
                  className="px-3 py-1.5 rounded text-xs normal-case tracking-normal"
                  style={{
                    background: 'var(--surface)',
                    color: 'var(--text)',
                    border: '1px solid rgba(232,224,208,0.18)',
                    opacity: gameTypeFilter === null ? 0.65 : 1,
                  }}
                >
                  {gameTypeDropdownOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                      {analysedCountsByType && option.value !== 'all'
                        ? ` (${analysedCountsByType[option.value as keyof AnalysedCountsByType]})`
                        : ''}
                    </option>
                  ))}
                </select>
              </label>
            </div>
          </div>

          {/* Aggregate stat cards */}
          <div className="grid grid-cols-3 gap-3 mb-6 sm:grid-cols-6">
            {statsLoading ? (
              Array.from({ length: 6 }).map((_, i) => <StatCardSkeleton key={i} />)
            ) : (
              <>
                <StatCard label="Analysed" helpText={STAT_HELP.analysed} value={String(stats?.games_analysed ?? 0)} color="var(--text)" />
                <StatCard
                  label="W / D / L"
                  helpText={STAT_HELP.wdl}
                  value={stats && stats.games_analysed > 0
                    ? `${stats.wins}W · ${stats.draws}D · ${stats.losses}L`
                    : '—'}
                  color="var(--text)"
                />
                <StatCard label="Avg CPL" helpText={STAT_HELP.avgCpl} value={fmt(stats?.avg_cp_loss ?? null, 1)} />
                <StatCard label="Blunders/game" helpText={STAT_HELP.blunders} value={fmt(stats?.blunders_per_game ?? null)} />
                <StatCard label="Mistakes/game" helpText={STAT_HELP.mistakes} value={fmt(stats?.mistakes_per_game ?? null)} />
                <StatCard label="Inaccuracies/game" helpText={STAT_HELP.inaccuracies} value={fmt(stats?.inaccuracies_per_game ?? null)} />
              </>
            )}
          </div>

          {/* Sparkline charts */}
          {!statsLoading && (
            <div className="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-3">
              <SparklinePanel
                title="Rating"
                subtitle={ratingData.length ? String(ratingData[ratingData.length - 1]) : '—'}
                data={ratingData}
                minValue={ratingMin}
                maxValue={ratingMax}
              />
              <SparklinePanel
                title="Avg CPL"
                subtitle={cpLossData.length ? fmt(cpLossData[cpLossData.length - 1]) : '—'}
                data={cpLossData}
                minValue={0}
                maxValue={cpLossMax}
              />
              <SparklinePanel
                title="Blunders/game"
                subtitle={blunderData.length ? String(blunderData[blunderData.length - 1]) : '—'}
                data={blunderData}
                minValue={0}
                maxValue={blunderMax}
              />
            </div>
          )}

          {/* Recent analysed games */}
          {!statsLoading && stats && stats.recent_games.length > 0 && (
            <div>
              <h3 className="text-xs uppercase tracking-wider mb-3" style={{ color: 'var(--text-muted)' }}>
                Recent analysed games
              </h3>
              <div className="rounded overflow-hidden" style={{ border: '1px solid rgba(232,224,208,0.10)' }}>
                <table className="w-full text-sm" style={{ borderCollapse: 'collapse' }}>
                  <thead>
                    <tr style={{ background: 'var(--surface)', borderBottom: '1px solid rgba(232,224,208,0.10)' }}>
                      {['Date', 'Opponent', 'Result', 'Avg CPL', 'Blunders', ''].map(h => (
                        <th key={h} className="text-left px-4 py-2 text-xs uppercase tracking-wider" style={{ color: 'var(--text-muted)', whiteSpace: 'nowrap' }}>
                          {h}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {stats.recent_games.map((g, i) => (
                      <tr key={g.share_code} style={{ borderBottom: i < stats.recent_games.length - 1 ? '1px solid rgba(232,224,208,0.06)' : undefined }}>
                        <td className="px-4 py-2.5" style={{ color: 'var(--text-muted)', fontFamily: 'var(--font-dm-mono)', fontSize: '12px' }}>
                          {g.played_at ?? '—'}
                        </td>
                        <td className="px-4 py-2.5" style={{ color: 'var(--text-muted)' }}>
                          {g.opponent_username ?? '—'}
                        </td>
                        <td className="px-4 py-2.5 font-medium" style={{ fontFamily: 'var(--font-dm-mono)', color: PLAYER_RESULT_COLOUR[g.result] ?? 'var(--text-muted)' }}>
                          {g.result}
                        </td>
                        <td className="px-4 py-2.5" style={{ fontFamily: 'var(--font-dm-mono)', color: 'var(--text-muted)' }}>
                          {fmt(g.avg_cp_loss)}
                        </td>
                        <td className="px-4 py-2.5 font-medium" style={{ fontFamily: 'var(--font-dm-mono)', color: g.blunder_count > 0 ? '#f87171' : 'var(--text-muted)' }}>
                          {g.blunder_count}
                        </td>
                        <td className="px-4 py-2.5 text-right">
                          <Link
                            href={`/g/${g.share_code}`}
                            className="text-xs px-3 py-1 rounded"
                            style={{ color: 'var(--gold)', border: '1px solid rgba(201,168,76,0.3)' }}
                          >
                            View →
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </section>

        {/* All games table */}
        <section>
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-base font-semibold" style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}>
              All games
            </h2>
            <span className="text-xs uppercase tracking-wider" style={{ color: 'var(--text-faint)' }}>
              {GAME_TYPE_OPTIONS.find(option => option.value === (gameTypeFilter ?? 'all'))?.label}
            </span>
          </div>

          {gamesLoading ? (
            <p style={{ color: 'var(--text-muted)' }}>Loading games…</p>
          ) : games.length === 0 ? (
            <div className="p-8 rounded text-center" style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}>
              <p style={{ color: 'var(--text-muted)' }}>No games imported yet. Press Sync Now to fetch your latest games.</p>
            </div>
          ) : (
            <>
              <div className="rounded overflow-hidden mb-6" style={{ border: '1px solid rgba(232,224,208,0.10)' }}>
                <table className="w-full text-sm" style={{ borderCollapse: 'collapse' }}>
                  <thead>
                    <tr style={{ background: 'var(--surface)', borderBottom: '1px solid rgba(232,224,208,0.10)' }}>
                      {['Result', 'Opponent', 'Time control', 'Blunders', 'Status', ''].map(h => (
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
                          <td className="px-4 py-3 font-medium" style={{ color: g.blunder_count != null && g.blunder_count > 0 ? '#f87171' : 'var(--text-muted)', fontFamily: 'var(--font-dm-mono)' }}>
                            {g.blunder_count ?? (g.analysis_status === 'complete' ? '0' : '—')}
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
        </section>
      </main>
    </>
  )
}

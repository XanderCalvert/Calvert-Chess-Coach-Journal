'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import Nav from '@/components/Nav'

interface ConnectedAccount {
  id: string
  platform: string
  username: string
  sync_status: 'never_synced' | 'syncing' | 'synced' | 'failed'
  last_synced_at: string | null
}

interface Quota {
  quota_limit: number | null
  quota_used: number | null
  quota_remaining: number | null
  quota_period_start: string | null
}

interface GameSummary {
  id: string
  connected_account_id: string | null
  share_code: string | null
  white_player: string
  black_player: string
  result: string
  played_at: string | null
  eco_code: string
  opening_name: string
  move_count: number
  analysis_status: 'pending' | 'queued' | 'analysing' | 'analysed' | 'failed'
  accuracy_pct: string | null
  blunder_count: number | null
  mistake_count: number | null
  inaccuracy_count: number | null
  user_colour: 'white' | 'black' | null
  opponent_username: string | null
}

const PLATFORM_LABEL: Record<string, string> = {
  chesscom: 'Chess.com',
  lichess: 'Lichess',
}

function userResultLabel(result: string, userColour: 'white' | 'black' | null): { label: string; color: string } {
  if (!userColour) {
    const raw = { white: '1-0', black: '0-1', draw: '½-½', unknown: '—' }
    return { label: raw[result as keyof typeof raw] ?? '—', color: 'var(--text-faint)' }
  }
  if (result === 'draw') return { label: 'Draw', color: 'var(--text-muted)' }
  if (result === userColour) return { label: 'Win', color: '#4ade80' }
  if (result === 'unknown') return { label: '—', color: 'var(--text-faint)' }
  return { label: 'Loss', color: '#f87171' }
}

const STATUS_STYLES: Record<string, { label: string; color: string }> = {
  pending:   { label: 'Not analysed', color: 'var(--text-muted)' },
  queued:    { label: 'Queued',       color: 'var(--gold)' },
  analysing: { label: 'Analysing…',  color: 'var(--gold)' },
  analysed:  { label: 'Complete',     color: '#4ade80' },
  failed:    { label: 'Failed',       color: '#f87171' },
}

const PAGE_SIZE = 20

const SYNC_POLL_MAX_TICKS = 90
const SYNC_POLL_MS = 2000

export default function GamesPage() {
  const router = useRouter()
  const syncPollsRef = useRef<Map<string, ReturnType<typeof setInterval>>>(new Map())

  const [accounts, setAccounts] = useState<ConnectedAccount[]>([])
  const [games, setGames] = useState<GameSummary[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [filterAccountId, setFilterAccountId] = useState<string | null>(null)
  const [quota, setQuota] = useState<Quota | null>(null)
  const [syncingIds, setSyncingIds] = useState<Set<string>>(new Set())
  const [syncMessages, setSyncMessages] = useState<Record<string, string>>({})
  const [analysingIds, setAnalysingIds] = useState<Set<string>>(new Set())
  const [analyseErrors, setAnalyseErrors] = useState<Record<string, string>>({})
  const [page, setPage] = useState(1)
  const gamesRef = useRef<GameSummary[]>([])
  gamesRef.current = games

  const clearAccountSyncPoll = (accountId: string) => {
    const t = syncPollsRef.current.get(accountId)
    if (t) {
      clearInterval(t)
      syncPollsRef.current.delete(accountId)
    }
  }

  useEffect(() => {
    return () => {
      for (const t of syncPollsRef.current.values()) {
        clearInterval(t)
      }
      syncPollsRef.current.clear()
    }
  }, [])

  const startPostSyncWatch = useCallback(
    (account: ConnectedAccount, baselineIds: Set<string>) => {
      clearAccountSyncPoll(account.id)
      let attempts = 0
      let navigated = false
      let tickRunning = false

      const finish = () => {
        clearAccountSyncPoll(account.id)
        setSyncingIds(prev => {
          const next = new Set(prev)
          next.delete(account.id)
          return next
        })
      }

      const tick = async () => {
        if (!syncPollsRef.current.has(account.id) || navigated || tickRunning) {
          return
        }
        tickRunning = true
        attempts += 1
        try {
          if (attempts > SYNC_POLL_MAX_TICKS) {
            if (!navigated) {
              setSyncMessages(prev => ({
                ...prev,
                [account.id]:
                  (prev[account.id] ? `${prev[account.id]} ` : '') +
                  'Stopped waiting for new games (timeout).',
              }))
            }
            finish()
            return
          }
          try {
            const [gamesRes, accountsRes] = await Promise.all([
              fetch('/api/games'),
              fetch('/api/connected-accounts'),
            ])
            if (!gamesRes.ok || !accountsRes.ok) {
              return
            }
            const gamesPayload = await gamesRes.json()
            const accountsPayload = await accountsRes.json()
            const nextGames: GameSummary[] = gamesPayload.data ?? gamesPayload
            const nextAccounts: ConnectedAccount[] = accountsPayload.data ?? []

            setGames(nextGames)
            setAccounts(nextAccounts)
            if (gamesPayload.quota) {
              setQuota(gamesPayload.quota)
            }

            if (!syncPollsRef.current.has(account.id)) {
              return
            }

            const forAccount = nextGames.filter(g => g.connected_account_id === account.id)
            const newOnes = forAccount.filter(g => !baselineIds.has(g.id))
            if (newOnes.length > 0) {
              const newest = [...newOnes].sort((a, b) => {
                const ta = a.played_at ? Date.parse(a.played_at) : 0
                const tb = b.played_at ? Date.parse(b.played_at) : 0
                if (tb !== ta) {
                  return tb - ta
                }
                return b.id.localeCompare(a.id)
              })[0]
              navigated = true
              router.push(`/games/${newest.id}/analysis`)
              finish()
              return
            }

            const acct = nextAccounts.find(a => a.id === account.id)
            if (acct && acct.sync_status !== 'syncing') {
              if (!navigated) {
                setSyncMessages(prev => ({
                  ...prev,
                  [account.id]: 'Sync finished. No new games in this run.',
                }))
              }
              finish()
            }
          } catch {
            /* next tick */
          }
        } finally {
          tickRunning = false
        }
      }

      const intervalId = setInterval(() => {
        void tick()
      }, SYNC_POLL_MS)
      syncPollsRef.current.set(account.id, intervalId)
      void tick()
    },
    [router],
  )

  useEffect(() => {
    async function load() {
      setLoading(true)
      setError(null)
      try {
        const [accountsRes, gamesRes] = await Promise.all([
          fetch('/api/connected-accounts'),
          fetch('/api/games'),
        ])
        if (!accountsRes.ok || !gamesRes.ok) {
          setError('Failed to load your games. Please refresh.')
          return
        }
        const accountsData = await accountsRes.json()
        setAccounts(accountsData.data ?? [])
        const gamesData = await gamesRes.json()
        setGames(gamesData.data ?? gamesData)
        if (gamesData.quota) setQuota(gamesData.quota)
      } catch {
        setError('Network error. Please refresh.')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [])

  async function handleSync(account: ConnectedAccount) {
    setSyncingIds(prev => new Set(prev).add(account.id))
    setSyncMessages(prev => ({ ...prev, [account.id]: '' }))

    try {
      const res = await fetch(
        `/api/connected-accounts/${account.platform}/${account.username}/sync`,
        { method: 'POST' },
      )
      if (res.status === 202) {
        const baselineIds = new Set(
          gamesRef.current.filter(g => g.connected_account_id === account.id).map(g => g.id),
        )
        setSyncMessages(prev => ({
          ...prev,
          [account.id]: 'Sync started. Opening the newest new game when it arrives…',
        }))
        setAccounts(prev =>
          prev.map(a => a.id === account.id ? { ...a, sync_status: 'syncing' } : a)
        )
        startPostSyncWatch(account, baselineIds)
      } else if (res.status === 409) {
        setSyncMessages(prev => ({ ...prev, [account.id]: 'Already syncing.' }))
      } else {
        setSyncMessages(prev => ({ ...prev, [account.id]: 'Sync failed. Please try again.' }))
        setSyncingIds(prev => { const s = new Set(prev); s.delete(account.id); return s })
      }
    } catch {
      setSyncMessages(prev => ({ ...prev, [account.id]: 'Could not reach server.' }))
      setSyncingIds(prev => { const s = new Set(prev); s.delete(account.id); return s })
    }
  }

  async function handleSyncFull(account: ConnectedAccount) {
    setSyncingIds(prev => new Set(prev).add(account.id))
    setSyncMessages(prev => ({ ...prev, [account.id]: '' }))

    try {
      const res = await fetch(
        `/api/connected-accounts/${account.platform}/${account.username}/sync-full`,
        { method: 'POST' },
      )
      if (res.status === 202) {
        const baselineIds = new Set(
          gamesRef.current.filter(g => g.connected_account_id === account.id).map(g => g.id),
        )
        setSyncMessages(prev => ({
          ...prev,
          [account.id]: 'Full sync started. Opening the newest new game when it arrives…',
        }))
        setAccounts(prev =>
          prev.map(a => a.id === account.id ? { ...a, sync_status: 'syncing' } : a)
        )
        startPostSyncWatch(account, baselineIds)
      } else if (res.status === 409) {
        setSyncMessages(prev => ({ ...prev, [account.id]: 'Already syncing.' }))
        setSyncingIds(prev => { const s = new Set(prev); s.delete(account.id); return s })
      } else {
        setSyncMessages(prev => ({ ...prev, [account.id]: 'Sync failed. Please try again.' }))
        setSyncingIds(prev => { const s = new Set(prev); s.delete(account.id); return s })
      }
    } catch {
      setSyncMessages(prev => ({ ...prev, [account.id]: 'Could not reach server.' }))
      setSyncingIds(prev => { const s = new Set(prev); s.delete(account.id); return s })
    }
  }

  async function handleAnalyse(gameId: string) {
    setAnalysingIds(prev => new Set(prev).add(gameId))
    setAnalyseErrors(prev => { const n = { ...prev }; delete n[gameId]; return n })
    setGames(prev => prev.map(g => g.id === gameId ? { ...g, analysis_status: 'queued' } : g))
    try {
      const res = await fetch(`/api/games/${gameId}/analyse`, { method: 'POST' })
      if (res.status === 422) {
        const body = await res.json().catch(() => ({}))
        const msg = body?.message ?? 'Monthly analysis quota exceeded.'
        setAnalyseErrors(prev => ({ ...prev, [gameId]: msg }))
        setGames(prev => prev.map(g => g.id === gameId ? { ...g, analysis_status: 'pending' } : g))
      } else if (res.status !== 202 && res.status !== 409) {
        setGames(prev => prev.map(g => g.id === gameId ? { ...g, analysis_status: 'pending' } : g))
      }
    } catch {
      setGames(prev => prev.map(g => g.id === gameId ? { ...g, analysis_status: 'pending' } : g))
    } finally {
      setAnalysingIds(prev => { const s = new Set(prev); s.delete(gameId); return s })
    }
  }

  const filteredGames = filterAccountId
    ? games.filter(g => g.connected_account_id === filterAccountId)
    : games

  const totalPages = Math.max(1, Math.ceil(filteredGames.length / PAGE_SIZE))
  const pageGames = filteredGames.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

  function setFilter(id: string | null) {
    setFilterAccountId(id)
    setPage(1)
  }

  return (
    <>
      <Nav />
      <main className="flex-1 px-6 py-10 max-w-5xl mx-auto w-full flex flex-col gap-8">

        {/* Accounts + sync */}
        {!loading && accounts.length > 0 && (
          <section className="flex flex-col gap-3">
            <h2 className="text-xs uppercase tracking-widest" style={{ color: 'var(--text-muted)' }}>
              Your accounts
            </h2>
            <div className="flex flex-wrap gap-3">
              {accounts.map(account => {
                const isSyncing = syncingIds.has(account.id) || account.sync_status === 'syncing'
                return (
                  <div
                    key={account.id}
                    className="flex items-center gap-4 px-4 py-3 rounded"
                    style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}
                  >
                    <div className="flex flex-col gap-0.5">
                      <span className="text-sm font-medium" style={{ color: 'var(--text)' }}>
                        {account.username}
                      </span>
                      <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                        {PLATFORM_LABEL[account.platform] ?? account.platform}
                        {account.last_synced_at && (
                          <> · Last synced {new Date(account.last_synced_at).toLocaleDateString()}</>
                        )}
                      </span>
                      {syncMessages[account.id] && (
                        <span className="text-xs mt-0.5" style={{ color: 'var(--gold)' }}>
                          {syncMessages[account.id]}
                        </span>
                      )}
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={() => handleSync(account)}
                        disabled={isSyncing}
                        className="text-xs px-3 py-1.5 rounded whitespace-nowrap"
                        style={{
                          background: isSyncing ? 'transparent' : 'var(--gold)',
                          color: isSyncing ? 'var(--text-muted)' : 'var(--bg)',
                          border: isSyncing ? '1px solid rgba(232,224,208,0.15)' : 'none',
                          cursor: isSyncing ? 'not-allowed' : 'pointer',
                        }}
                      >
                        {isSyncing ? 'Syncing…' : 'Sync now'}
                      </button>
                      {/* TODO: gate as premium-only once subscription flow exists */}
                      <button
                        onClick={() => handleSyncFull(account)}
                        disabled={isSyncing}
                        className="text-xs px-3 py-1.5 rounded whitespace-nowrap"
                        style={{
                          background: 'transparent',
                          color: isSyncing ? 'var(--text-faint)' : 'var(--text-muted)',
                          border: '1px solid rgba(232,224,208,0.15)',
                          cursor: isSyncing ? 'not-allowed' : 'pointer',
                        }}
                      >
                        Sync full history
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          </section>
        )}

        {/* Quota bar — free users only */}
        {!loading && quota && quota.quota_limit !== null && (
          quota.quota_remaining === 0 ? (
            <div
              className="px-4 py-3 rounded text-sm flex items-center justify-between gap-4"
              style={{ border: '1px solid rgba(201,168,76,0.35)', background: 'rgba(201,168,76,0.06)' }}
            >
              <span style={{ color: 'var(--gold)' }}>
                You&apos;ve used your {quota.quota_limit} free analyses this month.
              </span>
              <span className="text-xs whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                Upgrade for unlimited →
              </span>
            </div>
          ) : (
            <div className="flex items-center gap-3 text-xs" style={{ color: 'var(--text-muted)' }}>
              <span className="whitespace-nowrap">
                Analysis quota: {quota.quota_used} / {quota.quota_limit} used this month
              </span>
              <div
                className="flex-1 rounded-full overflow-hidden"
                style={{ height: 4, background: 'rgba(232,224,208,0.10)', maxWidth: 120 }}
              >
                <div
                  style={{
                    height: '100%',
                    width: `${Math.round(((quota.quota_used ?? 0) / quota.quota_limit) * 100)}%`,
                    background: (quota.quota_remaining ?? 0) <= 2 ? '#f87171' : 'var(--gold)',
                    borderRadius: 9999,
                  }}
                />
              </div>
              {quota.quota_period_start && (() => {
                const start = new Date(quota.quota_period_start + 'T00:00:00')
                const nextMonth = new Date(start.getFullYear(), start.getMonth() + 1, 1)
                return (
                  <span style={{ color: 'var(--text-faint)' }}>
                    Resets {nextMonth.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                  </span>
                )
              })()}
            </div>
          )
        )}

        {/* Games list */}
        <section className="flex flex-col gap-4">
          <div className="flex items-center justify-between flex-wrap gap-3">
            <div className="flex flex-col gap-0.5">
              <h1
                className="text-3xl font-semibold"
                style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}
              >
                My Games
              </h1>
              {!loading && !error && (() => {
                const analysedCount = filteredGames.filter(g => g.analysis_status === 'analysed').length
                const total = filteredGames.length
                return (
                  <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                    {total} {total === 1 ? 'game' : 'games'}
                    {filterAccountId && ' for this account'}
                    {total > 0 && (
                      <span style={{ color: 'var(--text-faint)' }}>
                        {' · '}{analysedCount} of {total} analysed
                      </span>
                    )}
                  </p>
                )
              })()}
            </div>

            {/* Account filter */}
            {!loading && accounts.length > 1 && (
              <div className="flex gap-2 flex-wrap">
                <button
                  onClick={() => setFilter(null)}
                  className="text-xs px-3 py-1.5 rounded"
                  style={{
                    background: filterAccountId === null ? 'var(--gold)' : 'var(--surface)',
                    color: filterAccountId === null ? 'var(--bg)' : 'var(--text-muted)',
                    border: '1px solid rgba(232,224,208,0.12)',
                  }}
                >
                  All
                </button>
                {accounts.map(account => (
                  <button
                    key={account.id}
                    onClick={() => setFilter(account.id)}
                    className="text-xs px-3 py-1.5 rounded"
                    style={{
                      background: filterAccountId === account.id ? 'var(--gold)' : 'var(--surface)',
                      color: filterAccountId === account.id ? 'var(--bg)' : 'var(--text-muted)',
                      border: '1px solid rgba(232,224,208,0.12)',
                    }}
                  >
                    {PLATFORM_LABEL[account.platform] ?? account.platform} · {account.username}
                  </button>
                ))}
              </div>
            )}
          </div>

          {loading && (
            <p style={{ color: 'var(--text-muted)' }}>Loading…</p>
          )}

          {error && (
            <div
              className="p-4 rounded text-sm"
              style={{ background: 'rgba(220,60,60,0.1)', border: '1px solid rgba(220,60,60,0.3)', color: 'var(--red)' }}
            >
              {error}
            </div>
          )}

          {!loading && !error && filteredGames.length === 0 && (
            <div
              className="p-10 rounded flex flex-col items-center gap-4 text-center"
              style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}
            >
              {accounts.length > 0 ? (
                <>
                  <p style={{ color: 'var(--text-muted)' }}>
                    No games synced yet. Sync your{' '}
                    {accounts.length === 1
                      ? `${PLATFORM_LABEL[accounts[0].platform] ?? accounts[0].platform} account`
                      : 'account'}{' '}
                    to import your recent games.
                  </p>
                  <button
                    onClick={() => handleSync(accounts[0])}
                    disabled={syncingIds.has(accounts[0].id)}
                    className="px-5 py-2 rounded text-sm font-medium"
                    style={{ background: 'var(--gold)', color: 'var(--bg)' }}
                  >
                    {syncingIds.has(accounts[0].id) ? 'Syncing…' : `Sync ${accounts[0].username}`}
                  </button>
                  <Link href="/import" className="text-xs" style={{ color: 'var(--text-muted)' }}>
                    Or import a PGN manually (for over-the-board games)
                  </Link>
                </>
              ) : (
                <>
                  <p style={{ color: 'var(--text-muted)' }}>No games yet.</p>
                  <Link href="/import" className="text-sm" style={{ color: 'var(--gold)' }}>
                    Import a PGN manually →
                  </Link>
                </>
              )}
            </div>
          )}

          {pageGames.length > 0 && (
            <>
              <div className="rounded overflow-hidden" style={{ border: '1px solid rgba(232,224,208,0.10)' }}>
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
                      const canAnalyse = g.analysis_status === 'pending' || g.analysis_status === 'failed'
                      const quotaExhausted = quota?.quota_remaining === 0 && quota?.quota_limit !== null
                      return (
                        <tr
                          key={g.id}
                          style={{
                            borderBottom: i < pageGames.length - 1 ? '1px solid rgba(232,224,208,0.06)' : undefined,
                          }}
                        >
                          <td className="px-4 py-3">
                            {(() => {
                              const opponent = g.opponent_username ?? (g.user_colour === 'white' ? g.black_player : g.white_player)
                              const { label, color } = userResultLabel(g.result, g.user_colour)
                              return (
                                <div className="flex items-center gap-2">
                                  <span
                                    title={g.user_colour === 'white' ? 'Playing White' : 'Playing Black'}
                                    style={{
                                      display: 'inline-block',
                                      width: 10,
                                      height: 10,
                                      borderRadius: 2,
                                      flexShrink: 0,
                                      background: g.user_colour === 'white' ? '#f0ead6' : '#1a1a1a',
                                      border: '1px solid rgba(232,224,208,0.30)',
                                    }}
                                  />
                                  <span style={{ color: 'var(--text)' }}>vs {opponent}</span>
                                  <span className="text-xs font-medium" style={{ color }}>{label}</span>
                                </div>
                              )
                            })()}
                          </td>
                          <td className="px-4 py-3" style={{ color: 'var(--text-muted)', maxWidth: '180px' }}>
                            <span className="block truncate">
                              {g.opening_name || '—'}
                              {g.eco_code && (
                                <span
                                  className="ml-1.5"
                                  style={{ color: 'var(--text-faint)', fontFamily: 'var(--font-dm-mono)', fontSize: '11px' }}
                                >
                                  {g.eco_code}
                                </span>
                              )}
                            </span>
                          </td>
                          <td
                            className="px-4 py-3 whitespace-nowrap"
                            style={{ color: 'var(--text-muted)', fontFamily: 'var(--font-dm-mono)', fontSize: '12px' }}
                          >
                            {g.played_at ?? '—'}
                          </td>
                          <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>
                            {g.move_count}
                          </td>
                          <td
                            className="px-4 py-3 font-medium"
                            style={{ color: 'var(--gold)', fontFamily: 'var(--font-dm-mono)' }}
                          >
                            {g.analysis_status === 'analysed' && g.accuracy_pct != null ? `${g.accuracy_pct}%` : '—'}
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap">
                            {canAnalyse ? (
                              <div className="flex flex-col gap-1">
                                <div className="flex items-center gap-2">
                                  <button
                                    onClick={() => !quotaExhausted && handleAnalyse(g.id)}
                                    disabled={analysingIds.has(g.id) || quotaExhausted}
                                    title={quotaExhausted ? 'Monthly quota reached — upgrade for unlimited' : undefined}
                                    className="text-xs px-3 py-1 rounded"
                                    style={{
                                      color: quotaExhausted ? 'var(--text-faint)' : 'var(--gold)',
                                      border: quotaExhausted ? '1px solid rgba(232,224,208,0.10)' : '1px solid rgba(201,168,76,0.35)',
                                      background: quotaExhausted ? 'transparent' : 'rgba(201,168,76,0.08)',
                                      cursor: (analysingIds.has(g.id) || quotaExhausted) ? 'not-allowed' : 'pointer',
                                      opacity: analysingIds.has(g.id) ? 0.6 : 1,
                                    }}
                                  >
                                    Analyse
                                  </button>
                                  {!quotaExhausted && quota?.quota_remaining !== null && (quota?.quota_remaining ?? 99) <= 2 && (
                                    <span className="text-xs" style={{ color: '#f87171' }}>
                                      {quota?.quota_remaining} left
                                    </span>
                                  )}
                                </div>
                                {analyseErrors[g.id] && (
                                  <span className="text-xs" style={{ color: '#f87171' }}>
                                    {analyseErrors[g.id]}
                                  </span>
                                )}
                              </div>
                            ) : (
                              <span className="text-xs" style={{ color: status.color }}>{status.label}</span>
                            )}
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

              <div className="flex items-center justify-between text-sm">
                <span style={{ color: 'var(--text-muted)' }}>
                  {filteredGames.length > PAGE_SIZE && `Page ${page} of ${totalPages}`}
                </span>
                <div className="flex gap-2">
                  {page > 1 && (
                    <button
                      onClick={() => setPage(p => p - 1)}
                      className="px-4 py-1.5 rounded"
                      style={{ background: 'var(--surface)', color: 'var(--text)', border: '1px solid rgba(232,224,208,0.12)' }}
                    >
                      ← Prev
                    </button>
                  )}
                  {page < totalPages && (
                    <button
                      onClick={() => setPage(p => p + 1)}
                      className="px-4 py-1.5 rounded"
                      style={{ background: 'var(--surface)', color: 'var(--text)', border: '1px solid rgba(232,224,208,0.12)' }}
                    >
                      Next →
                    </button>
                  )}
                </div>
              </div>
            </>
          )}
        </section>

        {/* Secondary action */}
        {!loading && filteredGames.length > 0 && (
          <p className="text-xs" style={{ color: 'var(--text-faint)' }}>
            Playing over the board?{' '}
            <Link href="/import" style={{ color: 'var(--text-muted)' }} className="underline">
              Import a PGN manually
            </Link>
          </p>
        )}

      </main>
    </>
  )
}

'use client'

import { useState, FormEvent, useEffect } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import type { AuthUser, ExplanationDepth } from '@/lib/auth-context'
import { useAuth } from '@/lib/auth-context'

interface ConnectedAccount {
  id: string
  platform: string
  username: string
  last_synced_at: string | null
  rapid_rating: number | null
  blitz_rating: number | null
  bullet_rating: number | null
}

interface GameOption {
  id: string
  white_player: string
  black_player: string
  played_at: string | null
  analysis_status: string
}

const PLATFORM_LABEL: Record<string, string> = {
  chesscom: 'Chess.com',
  lichess: 'Lichess',
}

const PLATFORMS = [
  { value: 'chesscom', label: 'Chess.com' },
  { value: 'lichess', label: 'Lichess' },
]

const EXPLANATION_LABEL: Record<ExplanationDepth, string> = {
  beginner: 'Beginner-friendly',
  club: 'Club player',
  experienced: 'Experienced',
}

const surfaceBorder = '1px solid rgba(232,224,208,0.10)'
const inputBorder = '1px solid rgba(232,224,208,0.18)'

function formatShortDate(iso: string | undefined) {
  if (!iso) return null
  try {
    return new Date(iso).toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  } catch {
    return null
  }
}

export default function SettingsClient({ user }: { user: AuthUser }) {
  const router = useRouter()
  const { logout } = useAuth()
  const [accounts, setAccounts] = useState<ConnectedAccount[]>([])
  const [loadingAccounts, setLoadingAccounts] = useState(true)
  const [idCopied, setIdCopied] = useState(false)

  const [showAddForm, setShowAddForm] = useState(false)
  const [addPlatform, setAddPlatform] = useState('chesscom')
  const [addUsername, setAddUsername] = useState('')
  const [addError, setAddError] = useState<string | null>(null)
  const [addLoading, setAddLoading] = useState(false)

  const [removingId, setRemovingId] = useState<string | null>(null)
  const [removeError, setRemoveError] = useState<string | null>(null)

  const isDev = process.env.NODE_ENV === 'development'
  const [settingsGames, setSettingsGames] = useState<GameOption[]>([])
  const [settingsGamesLoading, setSettingsGamesLoading] = useState(true)
  const [devGameId, setDevGameId] = useState('')
  const [devReanalyseBusy, setDevReanalyseBusy] = useState(false)
  const [devReanalyseMessage, setDevReanalyseMessage] = useState<string | null>(null)
  const [reanalyseCompletedBusy, setReanalyseCompletedBusy] = useState(false)
  const [reanalyseCompletedMessage, setReanalyseCompletedMessage] = useState<string | null>(null)

  const analysedGameCount = settingsGames.filter(g => g.analysis_status === 'analysed').length

  const memberSince = formatShortDate(user.created_at)
  const explanation =
    user.explanation_depth && EXPLANATION_LABEL[user.explanation_depth]
      ? EXPLANATION_LABEL[user.explanation_depth]
      : null

  useEffect(() => {
    fetch('/api/connected-accounts')
      .then(r => r.json())
      .then(data => setAccounts(data.data ?? []))
      .finally(() => setLoadingAccounts(false))
  }, [])

  useEffect(() => {
    setSettingsGamesLoading(true)
    fetch('/api/games')
      .then(r => (r.ok ? r.json() : null))
      .then(data => {
        const list = (data?.data ?? data) as GameOption[] | undefined
        if (!Array.isArray(list)) return
        const sorted = [...list].sort((a, b) => {
          const ta = a.played_at ? Date.parse(a.played_at) : 0
          const tb = b.played_at ? Date.parse(b.played_at) : 0
          return tb - ta
        })
        setSettingsGames(sorted)
        if (isDev) {
          setDevGameId(prev => prev || sorted[0]?.id || '')
        }
      })
      .finally(() => setSettingsGamesLoading(false))
  }, [isDev])

  async function copyAccountId() {
    try {
      await navigator.clipboard.writeText(user.id)
      setIdCopied(true)
      window.setTimeout(() => setIdCopied(false), 2000)
    } catch {
      /* ignore */
    }
  }

  async function handleAddAccount(e: FormEvent) {
    e.preventDefault()
    setAddError(null)
    setAddLoading(true)
    try {
      const res = await fetch('/api/connected-accounts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ platform: addPlatform, username: addUsername }),
      })
      if (!res.ok) {
        const data = await res.json().catch(() => ({}))
        setAddError((data as { message?: string }).message ?? 'Could not add account.')
        return
      }
      const account = await res.json()
      setAccounts(prev => [...prev, account])
      setAddUsername('')
      setShowAddForm(false)
    } catch {
      setAddError('An unexpected error occurred.')
    } finally {
      setAddLoading(false)
    }
  }

  async function handleRemove(id: string) {
    setRemoveError(null)
    setRemovingId(id)
    try {
      const res = await fetch(`/api/account/${id}`, { method: 'DELETE' })
      if (!res.ok) {
        setRemoveError('Could not remove account. Please try again.')
        return
      }
      const remaining = accounts.filter(a => a.id !== id)
      setAccounts(remaining)
      if (remaining.length === 0) {
        router.push('/onboarding')
      }
    } catch {
      setRemoveError('An unexpected error occurred.')
    } finally {
      setRemovingId(null)
    }
  }

  return (
    <div className="flex flex-col gap-12">
      {/* Account */}
      <section className="flex flex-col gap-4">
        <h2 className="text-xs uppercase tracking-widest" style={{ color: 'var(--text-muted)' }}>
          Account
        </h2>
        <div
          className="rounded-lg p-6 flex flex-col gap-5"
          style={{ background: 'var(--surface)', border: surfaceBorder }}
        >
          <dl className="flex flex-col gap-4 m-0">
            <div className="flex flex-col gap-0.5">
              <dt className="text-xs m-0" style={{ color: 'var(--text-muted)' }}>
                Display name
              </dt>
              <dd className="text-sm font-medium m-0" style={{ color: 'var(--text)' }}>
                {user.display_name ?? '—'}
              </dd>
            </div>
            <div className="flex flex-col gap-0.5">
              <dt className="text-xs m-0" style={{ color: 'var(--text-muted)' }}>
                Email
              </dt>
              <dd className="text-sm font-medium m-0" style={{ color: 'var(--text)' }}>
                {user.email}
              </dd>
              <span className="text-xs" style={{ color: 'var(--text-faint)' }}>
                {user.email_verified_at
                  ? 'Verified'
                  : 'Not verified — check your inbox if you need to confirm.'}
              </span>
            </div>
            {memberSince && (
              <div className="flex flex-col gap-0.5">
                <dt className="text-xs m-0" style={{ color: 'var(--text-muted)' }}>
                  Member since
                </dt>
                <dd className="text-sm m-0" style={{ color: 'var(--text)' }}>
                  {memberSince}
                </dd>
              </div>
            )}
            {explanation && (
              <div className="flex flex-col gap-0.5">
                <dt className="text-xs m-0" style={{ color: 'var(--text-muted)' }}>
                  Explanation style
                </dt>
                <dd className="text-sm m-0" style={{ color: 'var(--text)' }}>
                  {explanation}
                </dd>
                <span className="text-xs" style={{ color: 'var(--text-faint)' }}>
                  How detailed coach explanations are tuned for your level.
                </span>
              </div>
            )}
            {user.rating_estimate != null && user.rating_estimate > 0 && (
              <div className="flex flex-col gap-0.5">
                <dt className="text-xs m-0" style={{ color: 'var(--text-muted)' }}>
                  Estimated rating
                </dt>
                <dd className="text-sm m-0" style={{ color: 'var(--text)' }}>
                  {user.rating_estimate}
                </dd>
              </div>
            )}
            <div className="flex flex-col gap-1.5 pt-1">
              <dt className="text-xs m-0" style={{ color: 'var(--text-muted)' }}>
                Account ID
              </dt>
              <dd className="flex flex-wrap items-center gap-2 m-0">
                <code
                  className="text-xs px-2 py-1 rounded"
                  style={{
                    fontFamily: 'var(--font-dm-mono)',
                    background: 'rgba(232,224,208,0.06)',
                    color: 'var(--text-muted)',
                    wordBreak: 'break-all',
                  }}
                >
                  {user.id}
                </code>
                <button
                  type="button"
                  onClick={copyAccountId}
                  className="text-xs px-2.5 py-1 rounded transition-opacity"
                  style={{
                    background: 'transparent',
                    color: 'var(--gold)',
                    border: inputBorder,
                    cursor: 'pointer',
                  }}
                >
                  {idCopied ? 'Copied' : 'Copy'}
                </button>
              </dd>
              <span className="text-xs" style={{ color: 'var(--text-faint)' }}>
                Share this with support if you ever need help with your account.
              </span>
            </div>
          </dl>
        </div>
      </section>

      {/* Chess accounts */}
      <section className="flex flex-col gap-4">
        <h2 className="text-xs uppercase tracking-widest" style={{ color: 'var(--text-muted)' }}>
          Chess accounts
        </h2>

        {removeError && (
          <div
            className="p-4 rounded text-sm"
            style={{
              background: 'rgba(220,60,60,0.1)',
              border: '1px solid rgba(220,60,60,0.3)',
              color: 'var(--red)',
            }}
          >
            {removeError}
          </div>
        )}

        {loadingAccounts ? (
          <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
            Loading…
          </p>
        ) : accounts.length === 0 ? (
          <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
            No connected accounts.
          </p>
        ) : (
          <ul className="flex flex-col gap-3 list-none m-0 p-0">
            {accounts.map(account => (
              <li
                key={account.id}
                className="flex items-center justify-between gap-4 px-4 py-3 rounded-lg"
                style={{ background: 'var(--surface)', border: surfaceBorder }}
              >
                <div className="flex flex-col gap-0.5 min-w-0">
                  <span className="text-sm font-medium truncate" style={{ color: 'var(--text)' }}>
                    {account.username}
                  </span>
                  <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                    {PLATFORM_LABEL[account.platform] ?? account.platform}
                    {account.last_synced_at && (
                      <>
                        {' '}
                        · Last synced {formatShortDate(account.last_synced_at) ?? '—'}
                      </>
                    )}
                  </span>
                  {(account.rapid_rating || account.blitz_rating || account.bullet_rating) && (
                    <span className="text-xs" style={{ color: 'var(--text-faint)' }}>
                      {[
                        account.rapid_rating && `Rapid ${account.rapid_rating}`,
                        account.blitz_rating && `Blitz ${account.blitz_rating}`,
                        account.bullet_rating && `Bullet ${account.bullet_rating}`,
                      ]
                        .filter(Boolean)
                        .join(' · ')}
                    </span>
                  )}
                </div>
                <button
                  type="button"
                  onClick={() => handleRemove(account.id)}
                  disabled={removingId === account.id}
                  className="text-xs shrink-0 px-3 py-1.5 rounded transition-opacity disabled:opacity-50"
                  style={{
                    background: 'transparent',
                    color: 'var(--red)',
                    border: '1px solid rgba(220,60,60,0.35)',
                    cursor: removingId === account.id ? 'not-allowed' : 'pointer',
                  }}
                >
                  {removingId === account.id ? 'Removing…' : 'Remove'}
                </button>
              </li>
            ))}
          </ul>
        )}

        {showAddForm ? (
          <form
            onSubmit={handleAddAccount}
            className="flex flex-col gap-4 rounded-lg p-5"
            style={{ background: 'var(--surface)', border: surfaceBorder }}
          >
            <p className="text-xs m-0 leading-relaxed" style={{ color: 'var(--text-muted)' }}>
              Only connect chess accounts you own or have permission to analyse.
            </p>
            <div className="flex flex-col sm:flex-row gap-3">
              <select
                value={addPlatform}
                onChange={e => setAddPlatform(e.target.value)}
                className="rounded px-3 py-2 text-sm shrink-0"
                style={{
                  background: 'var(--surface)',
                  color: 'var(--text)',
                  border: inputBorder,
                }}
              >
                {PLATFORMS.map(p => (
                  <option key={p.value} value={p.value} style={{ background: '#181510', color: '#e8e0d0' }}>
                    {p.label}
                  </option>
                ))}
              </select>
              <input
                type="text"
                value={addUsername}
                onChange={e => setAddUsername(e.target.value)}
                required
                placeholder="Username on that site"
                className="rounded px-3 py-2 text-sm flex-1 min-w-0 placeholder:text-[rgba(232,224,208,0.35)]"
                style={{
                  background: 'rgba(15,13,11,0.5)',
                  color: 'var(--text)',
                  border: inputBorder,
                }}
              />
            </div>
            {addError && (
              <p className="text-xs m-0" style={{ color: 'var(--red)' }}>
                {addError}
              </p>
            )}
            <div className="flex flex-wrap gap-2">
              <button
                type="submit"
                disabled={addLoading}
                className="text-sm font-medium px-4 py-2 rounded disabled:opacity-50"
                style={{ background: 'var(--gold)', color: 'var(--bg)' }}
              >
                {addLoading ? 'Adding…' : 'Add account'}
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowAddForm(false)
                  setAddError(null)
                }}
                className="text-sm px-4 py-2 rounded"
                style={{
                  background: 'transparent',
                  color: 'var(--text-muted)',
                  border: inputBorder,
                }}
              >
                Cancel
              </button>
            </div>
          </form>
        ) : (
          <button
            type="button"
            onClick={() => setShowAddForm(true)}
            className="self-start text-sm font-medium px-4 py-2 rounded transition-opacity"
            style={{
              background: 'var(--surface)',
              color: 'var(--gold)',
              border: surfaceBorder,
            }}
          >
            + Add another account
          </button>
        )}
      </section>

      <section className="flex flex-col gap-4">
        <h2 className="text-xs uppercase tracking-widest" style={{ color: 'var(--text-muted)' }}>
          Analysis
        </h2>
        <div
          className="rounded-lg p-6 flex flex-col gap-4"
          style={{ background: 'var(--surface)', border: surfaceBorder }}
        >
          <p className="text-sm m-0 leading-relaxed" style={{ color: 'var(--text-muted)' }}>
            Re-run the engine on games that are already marked{' '}
            <span style={{ color: 'var(--text)' }}>analysed</span>. Newly
            synced games that have never been analysed are skipped — queue those from{' '}
            <Link href="/games" className="underline" style={{ color: 'var(--gold)' }}>
              My Games
            </Link>
            .
          </p>
          <p className="text-xs m-0 leading-relaxed" style={{ color: 'var(--text-faint)' }}>
            Newest games first, up to 200 per run. Each re-analysis counts toward your monthly analysis quota (same as
            clicking Analyse), unless the API is running with{' '}
            <code className="text-[11px]" style={{ color: 'var(--text-muted)' }}>
              APP_DEBUG=true
            </code>{' '}
            in <code className="text-[11px]" style={{ color: 'var(--text-muted)' }}>.env</code> — then quota is not
            applied (local development only). Failed or in-progress games are not included; retry those individually from
            My Games.
          </p>
          {settingsGamesLoading ? (
            <p className="text-xs m-0" style={{ color: 'var(--text-faint)' }}>
              Loading…
            </p>
          ) : (
            <p className="text-xs m-0" style={{ color: 'var(--text-muted)' }}>
              <span className="font-medium" style={{ color: 'var(--text)' }}>
                {analysedGameCount}
              </span>{' '}
              completed game{analysedGameCount === 1 ? '' : 's'} eligible to re-analyse.
            </p>
          )}
          <button
            type="button"
            disabled={reanalyseCompletedBusy || settingsGamesLoading || analysedGameCount === 0}
            onClick={async () => {
              setReanalyseCompletedMessage(null)
              setReanalyseCompletedBusy(true)
              try {
                const res = await fetch('/api/games/reanalyse-completed', { method: 'POST' })
                const body = await res.json().catch(() => ({}))
                const msg =
                  typeof body.message === 'string'
                    ? body.message
                    : res.ok
                      ? 'Request completed.'
                      : `Request failed (${res.status}).`
                setReanalyseCompletedMessage(msg)
                if (res.status === 202 || res.status === 200) {
                  const gamesRes = await fetch('/api/games')
                  if (gamesRes.ok) {
                    const data = await gamesRes.json()
                    const list = (data?.data ?? data) as GameOption[] | undefined
                    if (Array.isArray(list)) {
                      const sorted = [...list].sort((a, b) => {
                        const ta = a.played_at ? Date.parse(a.played_at) : 0
                        const tb = b.played_at ? Date.parse(b.played_at) : 0
                        return tb - ta
                      })
                      setSettingsGames(sorted)
                    }
                  }
                }
              } catch {
                setReanalyseCompletedMessage('Network error.')
              } finally {
                setReanalyseCompletedBusy(false)
              }
            }}
            className="self-start text-sm font-medium px-4 py-2 rounded disabled:opacity-50"
            style={{
              background: 'rgba(201,168,76,0.12)',
              color: 'var(--gold)',
              border: '1px solid rgba(201,168,76,0.35)',
              cursor: reanalyseCompletedBusy ? 'wait' : 'pointer',
            }}
          >
            {reanalyseCompletedBusy ? 'Queuing…' : 'Re-analyse completed games'}
          </button>
          {reanalyseCompletedMessage && (
            <p className="text-xs m-0" style={{ color: 'var(--text-muted)' }}>
              {reanalyseCompletedMessage}
            </p>
          )}
        </div>
      </section>

      {isDev && (
        <section className="flex flex-col gap-4">
          <h2 className="text-xs uppercase tracking-widest" style={{ color: 'var(--text-muted)' }}>
            Developer
          </h2>
          <div
            className="rounded-lg p-6 flex flex-col gap-4"
            style={{ background: 'rgba(100,100,180,0.06)', border: '1px solid rgba(160,160,220,0.22)' }}
          >
            <p className="text-sm m-0 leading-relaxed" style={{ color: 'var(--text-muted)' }}>
              Re-queue Stockfish for a game even when analysis is already complete. Requires{' '}
              <code className="text-xs" style={{ color: 'var(--text)' }}>
                APP_DEBUG=true
              </code>{' '}
              on the API. Does not consume monthly analysis quota. Run{' '}
              <code className="text-xs" style={{ color: 'var(--text)' }}>
                php artisan queue:work
              </code>{' '}
              so the job runs.
            </p>
            {settingsGamesLoading ? (
              <p className="text-xs m-0" style={{ color: 'var(--text-faint)' }}>
                Loading games…
              </p>
            ) : (
              <div className="flex flex-col gap-2">
                <label className="text-xs m-0" style={{ color: 'var(--text-muted)' }}>
                  Game
                </label>
                <select
                  value={settingsGames.some(g => g.id === devGameId) ? devGameId : ''}
                  onChange={e => {
                    const v = e.target.value
                    if (v) setDevGameId(v)
                  }}
                  className="rounded px-3 py-2 text-sm max-w-full"
                  style={{
                    background: 'var(--surface)',
                    color: 'var(--text)',
                    border: inputBorder,
                  }}
                >
                  <option value="" style={{ background: '#181510', color: '#e8e0d0' }}>
                    {settingsGames.length === 0 ? 'No games yet' : '— Choose from list —'}
                  </option>
                  {settingsGames.map(g => (
                    <option key={g.id} value={g.id} style={{ background: '#181510', color: '#e8e0d0' }}>
                      {g.played_at ?? '—'} · {g.white_player} vs {g.black_player} · {g.analysis_status}
                    </option>
                  ))}
                </select>
              </div>
            )}
            <div className="flex flex-col gap-1.5">
              <label className="text-xs m-0" style={{ color: 'var(--text-muted)' }}>
                Or paste game UUID
              </label>
              <input
                type="text"
                value={devGameId}
                onChange={e => setDevGameId(e.target.value.trim())}
                placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                className="rounded px-3 py-2 text-sm font-mono placeholder:text-[rgba(232,224,208,0.35)]"
                style={{
                  background: 'rgba(15,13,11,0.5)',
                  color: 'var(--text)',
                  border: inputBorder,
                }}
              />
            </div>
            <button
              type="button"
              disabled={devReanalyseBusy || !devGameId}
              onClick={async () => {
                setDevReanalyseMessage(null)
                setDevReanalyseBusy(true)
                try {
                  const res = await fetch(`/api/games/${devGameId}/analyse?force=1`, { method: 'POST' })
                  const body = await res.json().catch(() => ({}))
                  if (res.status === 202) {
                    setDevReanalyseMessage(
                      typeof body.message === 'string' ? body.message : 'Analysis re-queued. Check the game page for progress.',
                    )
                  } else {
                    setDevReanalyseMessage(
                      typeof body.message === 'string'
                        ? body.message
                        : `Request failed (${res.status}). Is APP_DEBUG enabled on the API?`,
                    )
                  }
                } catch {
                  setDevReanalyseMessage('Network error.')
                } finally {
                  setDevReanalyseBusy(false)
                }
              }}
              className="self-start text-sm font-medium px-4 py-2 rounded disabled:opacity-50"
              style={{
                background: 'rgba(160,160,220,0.2)',
                color: 'var(--text)',
                border: '1px solid rgba(160,160,220,0.35)',
                cursor: devReanalyseBusy ? 'wait' : 'pointer',
              }}
            >
              {devReanalyseBusy ? 'Queuing…' : 'Debug: Re-analyse game (force)'}
            </button>
            {devReanalyseMessage && (
              <p className="text-xs m-0 flex flex-col gap-2" style={{ color: 'var(--text-muted)' }}>
                <span>{devReanalyseMessage}</span>
                {devReanalyseMessage.includes('re-queued') && devGameId && (
                  <Link
                    href={`/games/${devGameId}/analysis`}
                    className="text-xs font-medium w-fit"
                    style={{ color: 'var(--gold)' }}
                  >
                    Open game analysis →
                  </Link>
                )}
              </p>
            )}
          </div>
        </section>
      )}

      {/* Session */}
      <section className="flex flex-col gap-4">
        <h2 className="text-xs uppercase tracking-widest" style={{ color: 'var(--text-muted)' }}>
          Session
        </h2>
        <div
          className="rounded-lg p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
          style={{ background: 'var(--surface)', border: surfaceBorder }}
        >
          <p className="text-sm m-0" style={{ color: 'var(--text-muted)' }}>
            Sign out on this device. You will need your email and password to sign in again.
          </p>
          <button
            type="button"
            onClick={() => logout()}
            className="text-sm font-medium px-5 py-2.5 rounded whitespace-nowrap shrink-0"
            style={{
              background: 'transparent',
              color: 'var(--text)',
              border: inputBorder,
            }}
          >
            Sign out
          </button>
        </div>
      </section>
    </div>
  )
}

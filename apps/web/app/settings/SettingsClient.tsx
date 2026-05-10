'use client'

import { useState, FormEvent, useEffect } from 'react'
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

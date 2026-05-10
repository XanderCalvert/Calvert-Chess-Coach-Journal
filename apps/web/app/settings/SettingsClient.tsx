'use client'

import { useState, FormEvent, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import type { AuthUser } from '@/lib/auth-context'

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

export default function SettingsClient({ user }: { user: AuthUser }) {
  const router = useRouter()
  const [accounts, setAccounts] = useState<ConnectedAccount[]>([])
  const [loadingAccounts, setLoadingAccounts] = useState(true)

  // Add account form state
  const [showAddForm, setShowAddForm] = useState(false)
  const [addPlatform, setAddPlatform] = useState('chesscom')
  const [addUsername, setAddUsername] = useState('')
  const [addError, setAddError] = useState<string | null>(null)
  const [addLoading, setAddLoading] = useState(false)

  // Remove confirmation state
  const [removingId, setRemovingId] = useState<string | null>(null)
  const [removeError, setRemoveError] = useState<string | null>(null)

  useEffect(() => {
    fetch('/api/connected-accounts')
      .then(r => r.json())
      .then(data => setAccounts(data.data ?? []))
      .finally(() => setLoadingAccounts(false))
  }, [])

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
      const res = await fetch(`/api/connected-accounts/${id}`, { method: 'DELETE' })
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
    <div className="flex flex-col gap-10">
      {/* Profile section */}
      <section className="flex flex-col gap-3">
        <h2 className="text-lg font-medium border-b pb-2">Profile</h2>
        <div className="flex flex-col gap-1">
          <span className="text-sm text-gray-500">Name</span>
          <span className="text-sm">{user.display_name ?? '—'}</span>
        </div>
        <div className="flex flex-col gap-1">
          <span className="text-sm text-gray-500">Email</span>
          <span className="text-sm">{user.email}</span>
        </div>
      </section>

      {/* Chess accounts section */}
      <section className="flex flex-col gap-4">
        <h2 className="text-lg font-medium border-b pb-2">Your chess accounts</h2>

        {removeError && <p className="text-red-600 text-sm">{removeError}</p>}

        {loadingAccounts ? (
          <p className="text-sm text-gray-500">Loading…</p>
        ) : (
          <ul className="flex flex-col gap-3">
            {accounts.map(account => (
              <li key={account.id} className="flex items-center justify-between border rounded px-4 py-3">
                <div className="flex flex-col gap-0.5">
                  <span className="text-sm font-medium">
                    <span className="text-xs text-gray-500 mr-2">
                      {PLATFORM_LABEL[account.platform] ?? account.platform}
                    </span>
                    {account.username}
                  </span>
                  {(account.rapid_rating || account.blitz_rating || account.bullet_rating) && (
                    <span className="text-xs text-gray-500">
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
                  onClick={() => handleRemove(account.id)}
                  disabled={removingId === account.id}
                  className="text-xs text-red-600 underline disabled:opacity-50"
                >
                  {removingId === account.id ? 'Removing…' : 'Remove'}
                </button>
              </li>
            ))}
          </ul>
        )}

        {showAddForm ? (
          <form onSubmit={handleAddAccount} className="flex flex-col gap-3 border rounded px-4 py-4">
            <p className="text-xs text-gray-500">
              Only connect chess accounts you own or have permission to analyse.
            </p>
            <div className="flex gap-3">
              <select
                value={addPlatform}
                onChange={e => setAddPlatform(e.target.value)}
                className="border rounded px-2 py-1.5 text-sm bg-white flex-shrink-0"
              >
                {PLATFORMS.map(p => (
                  <option key={p.value} value={p.value}>
                    {p.label}
                  </option>
                ))}
              </select>
              <input
                type="text"
                value={addUsername}
                onChange={e => setAddUsername(e.target.value)}
                required
                placeholder="username"
                className="border rounded px-3 py-1.5 text-sm flex-1 min-w-0"
              />
            </div>
            {addError && <p className="text-red-600 text-xs">{addError}</p>}
            <div className="flex gap-2">
              <button
                type="submit"
                disabled={addLoading}
                className="bg-black text-white rounded px-3 py-1.5 text-sm disabled:opacity-50"
              >
                {addLoading ? 'Adding…' : 'Add account'}
              </button>
              <button
                type="button"
                onClick={() => { setShowAddForm(false); setAddError(null) }}
                className="text-sm text-gray-600 underline"
              >
                Cancel
              </button>
            </div>
          </form>
        ) : (
          <button
            onClick={() => setShowAddForm(true)}
            className="self-start text-sm underline text-gray-700"
          >
            + Add another account
          </button>
        )}
      </section>
    </div>
  )
}

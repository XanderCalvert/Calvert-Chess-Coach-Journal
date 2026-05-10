'use client'

import { useState, FormEvent } from 'react'
import { useRouter } from 'next/navigation'

const PLATFORMS = [
  { value: 'chesscom', label: 'Chess.com' },
  { value: 'lichess', label: 'Lichess' },
]

export default function OnboardingForm() {
  const router = useRouter()
  const [platform, setPlatform] = useState('chesscom')
  const [username, setUsername] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setLoading(true)

    try {
      const res = await fetch('/api/connected-accounts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ platform, username }),
      })

      if (!res.ok) {
        const data = await res.json().catch(() => ({}))
        setError(
          (data as { message?: string }).message ??
            'Could not connect your account. Check the username and try again.',
        )
        return
      }

      router.push('/games')
    } catch {
      setError('An unexpected error occurred. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <label htmlFor="platform" className="text-sm font-medium">
          Platform
        </label>
        <select
          id="platform"
          value={platform}
          onChange={e => setPlatform(e.target.value)}
          className="border rounded px-3 py-2 text-sm bg-white"
        >
          {PLATFORMS.map(p => (
            <option key={p.value} value={p.value}>
              {p.label}
            </option>
          ))}
        </select>
      </div>

      <div className="flex flex-col gap-1">
        <label htmlFor="username" className="text-sm font-medium">
          Username
        </label>
        <input
          id="username"
          type="text"
          value={username}
          onChange={e => setUsername(e.target.value)}
          required
          autoComplete="off"
          placeholder={platform === 'chesscom' ? 'your Chess.com username' : 'your Lichess username'}
          className="border rounded px-3 py-2 text-sm"
        />
      </div>

      {error && <p className="text-red-600 text-sm">{error}</p>}

      <button
        type="submit"
        disabled={loading}
        className="bg-black text-white rounded px-4 py-2 text-sm font-medium disabled:opacity-50"
      >
        {loading ? 'Connecting…' : 'Connect account'}
      </button>
    </form>
  )
}

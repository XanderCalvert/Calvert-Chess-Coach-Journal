'use client'

import { useState, FormEvent } from 'react'
import { useRouter } from 'next/navigation'
import Nav from '@/components/Nav'

interface ImportSuccess {
  game_id: string
}

export default function ImportPage() {
  const router = useRouter()
  const [pgn, setPgn] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError(null)

    try {
      const res = await fetch('/api/import-pgn', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pgn }),
      })

      const data = await res.json()

      if (!res.ok) {
        setError(data.error ?? `Import failed (${res.status})`)
        return
      }

      router.push(`/games/${(data as ImportSuccess).game_id}/analysis`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Network error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <>
      <Nav />
      <main className="flex-1 px-6 py-12 max-w-2xl mx-auto w-full">
        <div className="mb-8">
          <div className="flex items-center gap-3 mb-2">
            <h1
              className="text-3xl font-semibold"
              style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}
            >
              Import a game
            </h1>
            <span
              className="text-[11px] font-medium px-2 py-0.5 rounded tracking-wider uppercase"
              style={{
                background: 'rgba(201,168,76,0.15)',
                color: 'var(--gold)',
                border: '1px solid rgba(201,168,76,0.3)',
              }}
            >
              Dev mode
            </span>
          </div>
          <p style={{ color: 'var(--text-muted)' }} className="text-sm">
            Paste a PGN to import the game and store every half-move with FEN positions.
            Games are saved under the dev seeder user — auth is not wired yet.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <textarea
            value={pgn}
            onChange={(e) => setPgn(e.target.value)}
            placeholder={'[White "Player"]\n[Black "Opponent"]\n[Result "1-0"]\n\n1. e4 e5 2. Nf3 ...'}
            rows={12}
            required
            className="w-full rounded p-3 text-sm resize-y"
            style={{
              fontFamily: 'var(--font-dm-mono)',
              background: 'var(--surface)',
              color: 'var(--text)',
              border: '1px solid rgba(232,224,208,0.12)',
              outline: 'none',
            }}
          />
          <button
            type="submit"
            disabled={loading || pgn.trim() === ''}
            className="self-start px-6 py-2.5 rounded text-sm font-medium transition-opacity"
            style={{
              background: 'var(--gold)',
              color: 'var(--bg)',
              opacity: loading || pgn.trim() === '' ? 0.5 : 1,
              cursor: loading || pgn.trim() === '' ? 'not-allowed' : 'pointer',
            }}
          >
            {loading ? 'Importing…' : 'Import'}
          </button>
        </form>

        {error && (
          <div
            className="mt-6 p-4 rounded text-sm"
            style={{
              background: 'rgba(220,60,60,0.1)',
              border: '1px solid rgba(220,60,60,0.3)',
              color: 'var(--red)',
            }}
          >
            {error}
          </div>
        )}

      </main>
    </>
  )
}

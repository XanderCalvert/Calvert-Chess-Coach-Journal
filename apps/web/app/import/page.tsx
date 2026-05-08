'use client'

import { useState, FormEvent } from 'react'
import Nav from '@/components/Nav'

interface ImportSuccess {
  game_id: string
  move_count: number
  parsed: {
    headers: {
      white: string
      black: string
      result: string
      ecoCode: string
      openingName: string
      playedAt: string | null
    }
  }
}

export default function ImportPage() {
  const [pgn, setPgn] = useState('')
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState<ImportSuccess | null>(null)
  const [error, setError] = useState<string | null>(null)

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

      const data = await res.json()

      if (!res.ok) {
        setError(data.error ?? `Import failed (${res.status})`)
        return
      }

      setResult(data as ImportSuccess)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Network error')
    } finally {
      setLoading(false)
    }
  }

  const resultLabel: Record<string, string> = {
    white: 'White wins',
    black: 'Black wins',
    draw: 'Draw',
    unknown: 'Unknown',
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

        {result && (
          <div
            className="mt-6 p-5 rounded"
            style={{
              background: 'var(--surface)',
              border: '1px solid rgba(232,224,208,0.12)',
            }}
          >
            <h2
              className="text-lg font-semibold mb-4"
              style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}
            >
              {result.parsed.headers.white} vs {result.parsed.headers.black}
            </h2>
            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
              {[
                ['Result', resultLabel[result.parsed.headers.result] ?? result.parsed.headers.result],
                ['ECO', result.parsed.headers.ecoCode || '—'],
                ['Opening', result.parsed.headers.openingName || '—'],
                ['Half-moves', result.move_count],
                ['Game ID', result.game_id],
              ].map(([label, value]) => (
                <div key={String(label)}>
                  <dt style={{ color: 'var(--text-muted)' }} className="text-xs uppercase tracking-wider mb-0.5">
                    {label}
                  </dt>
                  <dd
                    style={{
                      color: 'var(--text)',
                      fontFamily: label === 'Game ID' ? 'var(--font-dm-mono)' : undefined,
                      fontSize: label === 'Game ID' ? '11px' : undefined,
                      wordBreak: 'break-all',
                    }}
                  >
                    {value}
                  </dd>
                </div>
              ))}
            </dl>
          </div>
        )}
      </main>
    </>
  )
}

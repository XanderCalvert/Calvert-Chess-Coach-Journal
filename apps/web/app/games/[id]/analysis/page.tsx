'use client'

import { useEffect, useState } from 'react'
import { useParams } from 'next/navigation'
import Nav from '@/components/Nav'
import GameAnalysisView, { type GameAnalysis } from '@/components/GameAnalysisView'

export default function AnalysisPage() {
  const { id } = useParams<{ id: string }>()
  const [game, setGame]   = useState<GameAnalysis | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [polling, setPolling] = useState(false)
  const [copied, setCopied] = useState(false)

  function copyShareLink() {
    if (!game?.share_code) return
    navigator.clipboard.writeText(`${window.location.origin}/g/${game.share_code}`)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  useEffect(() => {
    let cancelled = false

    async function fetchGame() {
      const res = await fetch(`/api/games/${id}`)
      if (!res.ok) {
        const d = await res.json().catch(() => null)
        setError(d?.error ?? `Failed to load game (${res.status})`)
        return
      }
      const data: GameAnalysis = await res.json()
      if (!cancelled) {
        setGame(data)
        setPolling(data.analysis_status === 'pending' || data.analysis_status === 'running')
      }
    }

    fetchGame()
    return () => { cancelled = true }
  }, [id])

  useEffect(() => {
    if (!polling) return
    const interval = setInterval(async () => {
      const res = await fetch(`/api/games/${id}`)
      if (!res.ok) return
      const data: GameAnalysis = await res.json()
      setGame(data)
      if (data.analysis_status !== 'pending' && data.analysis_status !== 'running') {
        setPolling(false)
      }
    }, 3000)
    return () => clearInterval(interval)
  }, [polling, id])

  return (
    <>
      <Nav />
      <main className="flex-1 px-6 py-12 max-w-6xl mx-auto w-full">
        {error && (
          <div className="p-4 rounded text-sm" style={{ background: 'rgba(220,60,60,0.1)', border: '1px solid rgba(220,60,60,0.3)', color: 'var(--red)' }}>
            {error}
          </div>
        )}
        {!game && !error && (
          <p style={{ color: 'var(--text-muted)' }}>Loading…</p>
        )}
        {game?.share_code && (
          <div className="mb-4 flex justify-end">
            <button
              onClick={copyShareLink}
              style={{
                padding: '6px 14px',
                fontSize: '0.8rem',
                borderRadius: '6px',
                border: '1px solid var(--border)',
                background: copied ? 'rgba(80,200,120,0.15)' : 'var(--surface)',
                color: copied ? 'var(--green)' : 'var(--text-muted)',
                cursor: 'pointer',
                transition: 'all 0.15s',
              }}
            >
              {copied ? 'Copied!' : 'Copy share link'}
            </button>
          </div>
        )}
        {game && <GameAnalysisView game={game} />}
      </main>
    </>
  )
}

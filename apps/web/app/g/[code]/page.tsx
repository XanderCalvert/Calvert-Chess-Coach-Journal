'use client'

import { useEffect, useState } from 'react'
import { useParams } from 'next/navigation'
import Nav from '@/components/Nav'
import GameAnalysisView, { type GameAnalysis } from '@/components/GameAnalysisView'

export default function SharePage() {
  const { code } = useParams<{ code: string }>()
  const [game, setGame]   = useState<GameAnalysis | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [polling, setPolling] = useState(false)

  useEffect(() => {
    let cancelled = false

    async function fetchGame() {
      const res = await fetch(`/api/games/by-share-code/${code}`)
      if (!res.ok) {
        const d = await res.json().catch(() => null)
        if (res.status === 404) {
          setError('Game not found. This share link may be invalid.')
        } else {
          setError(d?.error ?? `Failed to load game (${res.status})`)
        }
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
  }, [code])

  useEffect(() => {
    if (!polling) return
    const interval = setInterval(async () => {
      const res = await fetch(`/api/games/by-share-code/${code}`)
      if (!res.ok) return
      const data: GameAnalysis = await res.json()
      setGame(data)
      if (data.analysis_status !== 'pending' && data.analysis_status !== 'running') {
        setPolling(false)
      }
    }, 3000)
    return () => clearInterval(interval)
  }, [polling, code])

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
        {game && <GameAnalysisView game={game} />}
      </main>
    </>
  )
}

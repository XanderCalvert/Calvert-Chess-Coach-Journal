'use client'

import { useEffect, useState, useCallback, useRef } from 'react'
import { useParams, useSearchParams, useRouter, usePathname } from 'next/navigation'
import Nav from '@/components/Nav'
import GameAnalysisView, { type GameAnalysis } from '@/components/GameAnalysisView'

export default function SharePage() {
  const { code }        = useParams<{ code: string }>()
  const searchParams    = useSearchParams()
  const router          = useRouter()
  const pathname        = usePathname()

  const rawPly  = parseInt(searchParams.get('ply') ?? '', 10)
  const initialPly = isNaN(rawPly) || rawPly < 0 ? 0 : rawPly

  const [game, setGame]   = useState<GameAnalysis | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [polling, setPolling] = useState(false)

  const gameRef = useRef<GameAnalysis | null>(null)
  const errorRef = useRef<string | null>(null)
  gameRef.current = game
  errorRef.current = error

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
        setPolling(data.analysis_status === 'pending' || data.analysis_status === 'queued' || data.analysis_status === 'analysing')
      }
    }

    fetchGame()
    return () => { cancelled = true }
  }, [code])

  // BFCache restore: React effects do not re-run, but we may still have game === null
  // if the in-flight fetch was dropped when leaving the page.
  useEffect(() => {
    function onPageShow(e: PageTransitionEvent) {
      if (!e.persisted) return
      if (gameRef.current != null || errorRef.current != null) return
      void (async () => {
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
        setGame(data)
        setPolling(data.analysis_status === 'pending' || data.analysis_status === 'queued' || data.analysis_status === 'analysing')
      })()
    }
    window.addEventListener('pageshow', onPageShow)
    return () => window.removeEventListener('pageshow', onPageShow)
  }, [code])

  useEffect(() => {
    if (!polling) return
    const interval = setInterval(async () => {
      const res = await fetch(`/api/games/by-share-code/${code}`)
      if (!res.ok) return
      const data: GameAnalysis = await res.json()
      setGame(data)
      if (data.analysis_status !== 'pending' && data.analysis_status !== 'queued' && data.analysis_status !== 'analysing') {
        setPolling(false)
      }
    }, 3000)
    return () => clearInterval(interval)
  }, [polling, code])

  const handlePlyChange = useCallback((ply: number) => {
    const currentRaw = parseInt(searchParams.get('ply') ?? '', 10)
    const currentPly = isNaN(currentRaw) || currentRaw < 0 ? 0 : currentRaw
    const nextPly = ply < 0 ? 0 : ply
    if (nextPly === currentPly) return

    const params = new URLSearchParams(searchParams.toString())
    if (ply === 0) {
      params.delete('ply')
    } else {
      params.set('ply', String(ply))
    }
    const queryString = params.toString()
    router.replace(queryString ? `${pathname}?${queryString}` : pathname, { scroll: false })
  }, [searchParams, router, pathname])

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
        {game && (
          <GameAnalysisView
            game={game}
            initialPly={initialPly}
            onPlyChange={handlePlyChange}
          />
        )}
      </main>
    </>
  )
}

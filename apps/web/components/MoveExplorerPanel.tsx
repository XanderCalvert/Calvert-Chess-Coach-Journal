'use client'

import { useEffect, useRef, useState } from 'react'
import { Chess } from 'chess.js'
import { useHintMode } from '@/contexts/HintModeContext'

interface Candidate {
  rank: number
  uci: string
  cp: number | null
  mate: number | null
  pv: string[]
}

interface AnalysisResponse {
  fen: string
  side_to_move: string
  engine_version: string
  candidates: Candidate[]
}

export interface MoveExplorerPanelProps {
  fen: string
  onTryMove: (newFen: string) => void
}

type PanelState = 'loading' | 'loaded' | 'error' | 'empty'
const SEARCH_TIME_MS = 1200
const REQUEST_DEBOUNCE_MS = 120

function formatEval(cp: number | null, mate: number | null): string {
  if (mate !== null) return mate > 0 ? `M${mate}` : `-M${Math.abs(mate)}`
  if (cp === null) return '0.00'
  const pawns = cp / 100
  return (pawns >= 0 ? '+' : '') + pawns.toFixed(2)
}

function uciToSan(chess: Chess, uci: string): string {
  try {
    const from = uci.slice(0, 2) as `${string}${string}`
    const to   = uci.slice(2, 4) as `${string}${string}`
    const promotion = uci[4] as 'q' | 'r' | 'b' | 'n' | undefined
    const move = chess.move({ from, to, promotion })
    return move?.san ?? uci
  } catch {
    return uci
  }
}

function deriveConfidence(delta: number): 'high' | 'medium' | 'low' {
  if (Math.abs(delta) <= 30)  return 'high'
  if (Math.abs(delta) <= 80)  return 'medium'
  return 'low'
}

const CONFIDENCE_STYLES: Record<string, { label: string; color: string }> = {
  high:   { label: 'Strong',    color: '#4ade80' },
  medium: { label: 'Solid',     color: '#facc15' },
  low:    { label: 'Risky',     color: '#fb923c' },
}

export default function MoveExplorerPanel({ fen, onTryMove }: MoveExplorerPanelProps) {
  const { hintMode } = useHintMode()
  const [state, setState]           = useState<PanelState>('loading')
  const [candidates, setCandidates] = useState<Candidate[]>([])
  const [sideToMove, setSideToMove] = useState<'white' | 'black' | null>(null)
  const [expandedPv, setExpandedPv] = useState<number | null>(null)
  const abortRef = useRef<AbortController | null>(null)
  const debounceRef = useRef<number | null>(null)
  const cacheRef = useRef<Map<string, AnalysisResponse>>(new Map())

  const applyResponse = (data: AnalysisResponse) => {
    const side = data.side_to_move?.toLowerCase() === 'black' ? 'black' : 'white'
    setSideToMove(side)
    setExpandedPv(null)
    if (data.candidates.length === 0) {
      setCandidates([])
      setState('empty')
      return
    }
    setCandidates(data.candidates)
    setState('loaded')
  }

  const fetchCandidates = (currentFen: string, immediate = false) => {
    const cached = cacheRef.current.get(currentFen)
    if (cached) {
      applyResponse(cached)
      return
    }

    if (debounceRef.current !== null) {
      window.clearTimeout(debounceRef.current)
      debounceRef.current = null
    }

    setState('loading')
    setExpandedPv(null)
    setSideToMove(null)

    const runFetch = () => {
      abortRef.current?.abort()
      const controller = new AbortController()
      abortRef.current = controller

      fetch('/api/positions/analyse', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ fen: currentFen, multipv: 3, time_ms: SEARCH_TIME_MS }),
        signal: controller.signal,
      })
        .then(res => {
          if (!res.ok) throw new Error(`HTTP ${res.status}`)
          return res.json() as Promise<AnalysisResponse>
        })
        .then(data => {
          cacheRef.current.set(currentFen, data)
          applyResponse(data)
        })
        .catch(err => {
          if (err instanceof Error && err.name === 'AbortError') return
          setState('error')
        })
    }

    if (immediate) {
      runFetch()
      return
    }

    debounceRef.current = window.setTimeout(runFetch, REQUEST_DEBOUNCE_MS)
  }

  useEffect(() => {
    fetchCandidates(fen)
    return () => {
      abortRef.current?.abort()
      if (debounceRef.current !== null) {
        window.clearTimeout(debounceRef.current)
      }
    }
  }, [fen])

  const panelStyle: React.CSSProperties = {
    background: 'var(--surface)',
    border: '1px solid rgba(232,224,208,0.10)',
    borderRadius: 6,
    padding: 16,
  }

  const monoStyle: React.CSSProperties = {
    fontFamily: 'var(--font-dm-mono)',
    fontSize: 13,
  }

  if (state === 'loading') {
    return (
      <div style={panelStyle}>
        <p style={{ ...monoStyle, color: 'var(--text-faint)' }}>Analysing position…</p>
        <div className="flex flex-col gap-2 mt-3">
          {[1, 2, 3].map(i => (
            <div
              key={i}
              style={{ height: 48, borderRadius: 4, background: 'rgba(232,224,208,0.06)' }}
            />
          ))}
        </div>
      </div>
    )
  }

  if (state === 'error') {
    return (
      <div style={panelStyle}>
        <p style={{ ...monoStyle, color: '#f87171', marginBottom: 8 }}>Analysis failed.</p>
        <button
          onClick={() => fetchCandidates(fen, true)}
          style={{
            ...monoStyle,
            background: 'rgba(232,224,208,0.1)',
            border: '1px solid rgba(232,224,208,0.2)',
            borderRadius: 4,
            color: 'var(--text)',
            cursor: 'pointer',
            padding: '4px 12px',
          }}
        >
          Retry
        </button>
      </div>
    )
  }

  if (state === 'empty') {
    return (
      <div style={panelStyle}>
        <p style={{ ...monoStyle, color: 'var(--text-faint)' }}>No legal moves from this position.</p>
      </div>
    )
  }

  const rank1Eval = candidates[0]?.cp ?? 0

  return (
    <div style={panelStyle} className="flex flex-col gap-2">
      <p style={{ ...monoStyle, color: 'var(--text-muted)', marginBottom: 0 }}>Candidate moves</p>
      {sideToMove && (
        <p style={{ ...monoStyle, color: 'var(--text-faint)', fontSize: 11, marginBottom: 4 }}>
          {sideToMove === 'white' ? 'White to move' : 'Black to move'}
        </p>
      )}

      {candidates.map(candidate => {
        const chess = new Chess(fen)
        const san   = uciToSan(chess, candidate.uci)

        const newChess = new Chess(fen)
        let newFen = fen
        try {
          newChess.move({ from: candidate.uci.slice(0, 2), to: candidate.uci.slice(2, 4), promotion: candidate.uci[4] as 'q' | undefined })
          newFen = newChess.fen()
        } catch {
          // leave newFen as current fen if move fails
        }

        const evalStr   = formatEval(candidate.cp, candidate.mate)
        const delta     = candidate.cp !== null ? candidate.cp - rank1Eval : 0
        const deltaStr  = candidate.rank === 1 ? '' : (delta >= 0 ? `+${(delta / 100).toFixed(2)}` : (delta / 100).toFixed(2))
        const confidence = deriveConfidence(delta)
        const confStyle  = CONFIDENCE_STYLES[confidence]
        const isExpanded = expandedPv === candidate.rank

        return (
          <div
            key={candidate.rank}
            style={{
              background: 'rgba(232,224,208,0.05)',
              border: '1px solid rgba(232,224,208,0.10)',
              borderRadius: 4,
              padding: '8px 12px',
            }}
          >
            <div className="flex items-center gap-3">
              <span style={{ ...monoStyle, color: 'var(--text-faint)', width: 16 }}>{candidate.rank}.</span>
              <span style={{ ...monoStyle, color: 'var(--text)', fontWeight: 600, fontSize: 15 }}>{san}</span>

              {/* Confidence badge — shown in guided + full */}
              {hintMode === 'full' && (
                <span
                  style={{ ...monoStyle, fontSize: 11, color: confStyle.color }}
                >
                  {confStyle.label}
                </span>
              )}

              {/* Eval — hidden in training mode */}
              {hintMode === 'full' && (
                <>
                  <span style={{ ...monoStyle, color: 'var(--gold)', marginLeft: 'auto' }}>{evalStr}</span>
                  {deltaStr && (
                    <span style={{ ...monoStyle, color: '#f87171', fontSize: 11 }}>{deltaStr}</span>
                  )}
                </>
              )}

              {/* PV toggle — hidden in training mode */}
              {hintMode === 'full' && (
                <button
                  onClick={() => setExpandedPv(isExpanded ? null : candidate.rank)}
                  style={{
                    ...monoStyle,
                    background: 'none',
                    border: 'none',
                    color: 'var(--text-faint)',
                    cursor: 'pointer',
                    fontSize: 11,
                    padding: '0 4px',
                    marginLeft: undefined,
                  }}
                  aria-label={isExpanded ? 'Collapse PV' : 'Expand PV'}
                >
                  {isExpanded ? '▲' : '▼'}
                </button>
              )}

              <button
                onClick={() => onTryMove(newFen)}
                style={{
                  ...monoStyle,
                  background: 'rgba(232,224,208,0.1)',
                  border: '1px solid rgba(232,224,208,0.2)',
                  borderRadius: 3,
                  color: 'var(--text)',
                  cursor: 'pointer',
                  fontSize: 11,
                  padding: '2px 8px',
                  marginLeft: hintMode === 'guided' ? 'auto' : undefined,
                }}
              >
                Try
              </button>
            </div>

            {isExpanded && candidate.pv.length > 0 && hintMode === 'full' && (
              <div style={{ ...monoStyle, color: 'var(--text-muted)', fontSize: 11, marginTop: 6, paddingLeft: 20 }}>
                {candidate.pv.join(' ')}
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}

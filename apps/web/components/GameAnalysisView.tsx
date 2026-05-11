'use client'

import dynamic from 'next/dynamic'
import { useState, useCallback, useEffect, useRef } from 'react'
import MoveNavControls from '@/components/MoveNavControls'
import MoveDetailPanel from '@/components/MoveDetailPanel'
import MoveExplorerPanel from '@/components/MoveExplorerPanel'
import KeyMomentsPanel from '@/components/KeyMomentsPanel'
import { HintModeProvider, useHintMode, type HintMode } from '@/contexts/HintModeContext'

const ChessBoardViewer = dynamic(() => import('@/components/ChessBoardViewer'), {
  ssr: false,
  loading: () => (
    <div
      className="w-full animate-pulse"
      style={{ aspectRatio: '1 / 1', background: 'var(--surface)', borderRadius: 4 }}
    />
  ),
})

export interface ThreatAwareness {
  threats_before: string[]
  threats_after: string[]
  response: 'addressed' | 'not_addressed' | 'unknown' | 'none'
  confidence: 'low' | 'medium' | 'high'
}

export interface Move {
  id: string
  move_number: number
  colour: 'white' | 'black'
  san: string
  uci: string
  fen_before: string
  fen_after: string
  cp_score: number | null
  cp_loss: number | null
  classification: string | null
  themes: string[]
  tactical_flags: string[]
  threat_awareness: ThreatAwareness | null
  risk_note: string | null
  best_move_san: string | null
  best_move_uci: string | null
  best_line: string[]
  game_phase: 'opening' | 'middlegame' | 'endgame' | null
}

export interface KeyMoment {
  rank: number
  move_id: string
  move_number: number
  colour: 'white' | 'black'
  san: string
  cp_loss: number
  classification: string | null
  game_phase: string
  best_move_uci: string | null
  best_move_san: string | null
  risk_note: string | null
  explanation_text: string | null
}

export interface GameAnalysis {
  id: string
  white_player: string
  black_player: string
  result: string
  played_at: string | null
  eco_code: string
  opening_name: string
  move_count: number
  analysis_status: 'pending' | 'queued' | 'analysing' | 'analysed' | 'failed'
  accuracy_pct: string | null
  blunder_count: number | null
  mistake_count: number | null
  inaccuracy_count: number | null
  user_colour: 'white' | 'black' | null
  share_code: string | null
  source_url?: string | null
  moves: Move[]
  key_moments?: KeyMoment[]
}

export const CLASSIFICATION_STYLES: Record<string, { label: string; color: string; bg: string }> = {
  best:       { label: 'Best',      color: '#4ade80', bg: 'rgba(74,222,128,0.12)' },
  excellent:  { label: 'Excellent', color: '#4ade80', bg: 'rgba(74,222,128,0.10)' },
  good:       { label: 'Good',      color: '#86efac', bg: 'rgba(134,239,172,0.10)' },
  inaccuracy: { label: '?!',        color: '#facc15', bg: 'rgba(250,204,21,0.12)' },
  mistake:    { label: '?',         color: '#fb923c', bg: 'rgba(251,146,60,0.12)' },
  blunder:    { label: '??',        color: '#f87171', bg: 'rgba(248,113,113,0.12)' },
}

const RESULT_LABEL: Record<string, string> = {
  white:   'White wins',
  black:   'Black wins',
  draw:    'Draw',
  unknown: '—',
}

const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1'
const ECO_OPENING_FALLBACK: Record<string, string> = {
  A45: "Queen's Pawn Game",
  A46: 'Queen\'s Pawn Game: Torre Attack',
  B00: 'Uncommon Opening',
  B01: 'Scandinavian Defense',
  B06: 'Modern Defense',
  B07: 'Pirc Defense',
  B12: 'Caro-Kann Defense',
  B20: 'Sicilian Defense',
  C00: 'French Defense',
  C20: "King's Pawn Game",
  C40: "King's Knight Opening",
  C50: 'Italian Game',
  C60: 'Ruy Lopez',
  D00: "Queen's Pawn Game",
  D02: "Queen's Pawn Game: London System",
  D06: "Queen's Gambit",
  D10: 'Slav Defense',
  D30: "Queen's Gambit Declined",
  E00: "Queen's Pawn Game: Catalan",
  E20: 'Nimzo-Indian Defense',
}

function resolveOpeningName(openingName: string, ecoCode: string): string {
  if (openingName && openingName.toLowerCase() !== 'unknown') {
    return openingName
  }

  const normalizedEco = ecoCode.trim().toUpperCase()
  if (!normalizedEco) return openingName || 'Unknown'
  return ECO_OPENING_FALLBACK[normalizedEco] ?? (openingName || 'Unknown')
}

function estimatePlayedElo({
  estimatedAccuracy,
  blunders,
  mistakes,
  inaccuracies,
}: {
  estimatedAccuracy: number
  blunders: number
  mistakes: number
  inaccuracies: number
}): number {
  // Chessigma-style single-game proxy:
  // map game accuracy to a low-range "played Elo", then penalize major errors.
  const raw = (estimatedAccuracy * 7.5) - 40 - (blunders * 70) - (mistakes * 45) - (inaccuracies * 15)
  return Math.round(Math.max(100, Math.min(2800, raw)))
}

function StatCard({ label, value, color }: { label: string; value: string | number; color?: string }) {
  return (
    <div
      className="rounded p-4 flex flex-col gap-1"
      style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}
    >
      <span className="text-xs uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>{label}</span>
      <span className="text-2xl font-semibold" style={{ color: color ?? 'var(--text)' }}>{value}</span>
    </div>
  )
}

const HINT_MODES: { value: HintMode; label: string }[] = [
  { value: 'training', label: 'Training' },
  { value: 'guided',   label: 'Guided' },
  { value: 'full',     label: 'Full Analysis' },
]

function HintModeToggle() {
  const { hintMode, setHintMode } = useHintMode()
  return (
    <div className="flex items-center gap-2 mb-4">
      <span className="text-xs uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>Mode</span>
      <div
        className="flex rounded overflow-hidden"
        style={{ border: '1px solid var(--border)' }}
      >
        {HINT_MODES.map(({ value, label }) => (
          <button
            key={value}
            onClick={() => setHintMode(value)}
            className="px-3 py-1 text-xs transition-colors"
            style={{
              background: hintMode === value ? 'rgba(201,168,76,0.20)' : 'var(--surface)',
              color: hintMode === value ? 'var(--gold)' : 'var(--text-muted)',
              borderRight: '1px solid var(--border)',
              cursor: 'pointer',
            }}
          >
            {label}
          </button>
        ))}
      </div>
    </div>
  )
}

function StatsLegend() {
  return (
    <details
      className="rounded p-4 mb-8"
      style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}
    >
      <summary
        className="cursor-pointer text-sm font-medium"
        style={{ color: 'var(--text)' }}
      >
        Stats legend — how these numbers are calculated
      </summary>
      <div className="mt-3 text-sm flex flex-col gap-2" style={{ color: 'var(--text-muted)' }}>
        <p>
          <strong style={{ color: 'var(--text)' }}>Accuracy</strong>: overall game accuracy from the analysis pipeline.
        </p>
        <p>
          <strong style={{ color: 'var(--text)' }}>Accuracy (W/B)</strong>: side-level accuracy proxy estimated from average centipawn loss for White and Black.
        </p>
        <p>
          <strong style={{ color: 'var(--text)' }}>Elo (Est, W/B)</strong>: single-game played-strength estimate derived from side accuracy proxy and error counts. It is not an official rating.
        </p>
        <p>
          <strong style={{ color: 'var(--text)' }}>Blunders / Mistakes / Inaccuracies (W/B)</strong>: count of classified errors for White and Black in this game.
        </p>
        <p>
          <strong style={{ color: 'var(--text)' }}>Loss (cp)</strong>: centipawns dropped on the played move versus the engine best move (higher means bigger mistake).
        </p>
        <p>
          <strong style={{ color: 'var(--text)' }}>Eval (cp)</strong>: position evaluation in centipawns after the move; positive favors White, negative favors Black.
        </p>
        <p>
          <strong style={{ color: 'var(--text)' }}>Candidate numbers</strong>: left value is the candidate eval; small red value is how much worse it is than rank 1 (best) in pawns.
        </p>
        <p>
          <strong style={{ color: 'var(--text)' }}>Move labels</strong>: <strong style={{ color: '#4ade80' }}>Best</strong>, <strong style={{ color: '#4ade80' }}>Excellent</strong>, <strong style={{ color: '#86efac' }}>Good</strong>, <strong style={{ color: '#facc15' }}>?!</strong> (inaccuracy), <strong style={{ color: '#fb923c' }}>?</strong> (mistake), <strong style={{ color: '#f87171' }}>??</strong> (blunder).
        </p>
        <p>
          <strong style={{ color: 'var(--text)' }}>Book</strong>: opening-book phase marker from the game opening tag (early opening moves, not an official per-move engine book lookup).
        </p>
      </div>
    </details>
  )
}

function MoveCell({
  move,
  isActive,
  isBookMove,
  onClick,
}: {
  move: Move | null
  isActive: boolean
  isBookMove: boolean
  onClick: () => void
}) {
  if (!move) return <span />
  const style = move.classification ? CLASSIFICATION_STYLES[move.classification] : null

  return (
    <button
      onClick={onClick}
      data-active={isActive ? 'true' : undefined}
      className="flex items-center gap-2 w-full text-left px-2 py-1 rounded transition-colors"
      style={{
        fontFamily: 'var(--font-dm-mono)',
        background: isActive ? 'rgba(201,168,76,0.15)' : 'transparent',
        borderLeft: isActive ? '2px solid var(--gold)' : '2px solid transparent',
        cursor: 'pointer',
      }}
    >
      <span style={{ color: 'var(--text)' }}>{move.san}</span>
      {isBookMove && (
        <span
          className="text-xs px-1.5 py-0.5 rounded font-medium"
          style={{ color: '#93c5fd', background: 'rgba(147,197,253,0.15)' }}
        >
          Book
        </span>
      )}
      {style && (
        <span
          className="text-xs px-1.5 py-0.5 rounded font-medium"
          style={{ color: style.color, background: style.bg }}
        >
          {style.label}
        </span>
      )}
      {move.cp_loss != null && move.cp_loss > 0 && (
        <span className="text-xs" style={{ color: 'var(--text-faint)' }}>
          -{move.cp_loss}
        </span>
      )}
    </button>
  )
}

interface Props {
  game: GameAnalysis
  initialPly?: number
  onPlyChange?: (ply: number) => void
  onRequestAnalysis?: () => void
  analyseError?: string | null
}

export default function GameAnalysisView(props: Props) {
  return (
    <HintModeProvider>
      <GameAnalysisViewInner {...props} />
    </HintModeProvider>
  )
}

function GameAnalysisViewInner({
  game,
  initialPly,
  onPlyChange,
  onRequestAnalysis,
  analyseError,
}: Props) {
  const moves = game.moves
  const resolvedOpeningName = resolveOpeningName(game.opening_name, game.eco_code)
  const hasKnownOpening = Boolean(resolvedOpeningName && resolvedOpeningName.toLowerCase() !== 'unknown')
  const openingBookPlyLimit = 10
  const openingLabel = hasKnownOpening
    ? `${resolvedOpeningName}${game.eco_code ? ` (${game.eco_code})` : ''}`
    : null
  const whiteMoves = moves.filter(move => move.colour === 'white')
  const blackMoves = moves.filter(move => move.colour === 'black')

  const countByClassification = (sideMoves: Move[], target: string) =>
    sideMoves.reduce((count, move) => count + (move.classification === target ? 1 : 0), 0)

  const averageCpLoss = (sideMoves: Move[]) => {
    const losses = sideMoves
      .map(move => move.cp_loss)
      .filter((value): value is number => value != null && value >= 0)
    if (losses.length === 0) return 0
    return losses.reduce((sum, value) => sum + value, 0) / losses.length
  }

  const estimateAccuracyFromCpLoss = (avgCpLoss: number) => {
    // Exponential drop-off gives a stable 0–100 proxy from centipawn loss.
    const accuracy = 100 * Math.exp(-avgCpLoss / 120)
    return Math.max(0, Math.min(100, accuracy))
  }

  const whiteStats = {
    blunders: countByClassification(whiteMoves, 'blunder'),
    mistakes: countByClassification(whiteMoves, 'mistake'),
    inaccuracies: countByClassification(whiteMoves, 'inaccuracy'),
    avgCpLoss: averageCpLoss(whiteMoves),
  }

  const blackStats = {
    blunders: countByClassification(blackMoves, 'blunder'),
    mistakes: countByClassification(blackMoves, 'mistake'),
    inaccuracies: countByClassification(blackMoves, 'inaccuracy'),
    avgCpLoss: averageCpLoss(blackMoves),
  }

  const whiteAccuracyEstimate = estimateAccuracyFromCpLoss(whiteStats.avgCpLoss)
  const blackAccuracyEstimate = estimateAccuracyFromCpLoss(blackStats.avgCpLoss)

  const whiteEloEstimate = estimatePlayedElo({ ...whiteStats, estimatedAccuracy: whiteAccuracyEstimate })
  const blackEloEstimate = estimatePlayedElo({ ...blackStats, estimatedAccuracy: blackAccuracyEstimate })

  const clampedInitial =
    initialPly !== undefined
      ? Math.min(Math.max(initialPly - 1, -1), moves.length - 1)
      : -1

  const [currentMoveIndex, setCurrentMoveIndex] = useState<number>(clampedInitial)
  const [explorerFen, setExplorerFen]   = useState<string | null>(null)
  const [copiedShare, setCopiedShare]   = useState(false)
  const [copiedPos, setCopiedPos]       = useState(false)
  const moveListRef    = useRef<HTMLTableSectionElement>(null)
  const onPlyChangeRef = useRef(onPlyChange)
  onPlyChangeRef.current = onPlyChange

  const replayFen =
    currentMoveIndex === -1
      ? START_FEN
      : (moves[currentMoveIndex]?.fen_after ?? START_FEN)

  const explorerBaseFen =
    currentMoveIndex === -1
      ? START_FEN
      : (moves[currentMoveIndex]?.fen_before ?? START_FEN)

  const isExplorerMode = explorerFen !== null
  const displayedFen   = explorerFen ?? replayFen
  // Keep currentFen as an alias for the displayed FEN so existing references still work
  const currentFen = displayedFen

  // UCI is 4–5 chars (e.g., "g1f3" or "e7e8q"). We use only the first 4 chars —
  // the promotion piece char (5th) doesn't affect square highlighting.
  const lastMoveUci = currentMoveIndex >= 0 ? moves[currentMoveIndex]?.uci ?? null : null
  const lastMove = lastMoveUci
    ? { from: lastMoveUci.slice(0, 2) as string, to: lastMoveUci.slice(2, 4) as string }
    : null

  const boardOrientation = game.user_colour === 'black' ? 'black' : 'white'
  const isPending = game.analysis_status === 'pending' || game.analysis_status === 'queued' || game.analysis_status === 'analysing'

  const goFirst   = useCallback(() => { setCurrentMoveIndex(-1); setExplorerFen(null) }, [])
  const goPrev    = useCallback(() => { setCurrentMoveIndex(i => Math.max(-1, i - 1)); setExplorerFen(null) }, [])
  const goNext    = useCallback(() => { setCurrentMoveIndex(i => Math.min(moves.length - 1, i + 1)); setExplorerFen(null) }, [moves.length])
  const goLast    = useCallback(() => { setCurrentMoveIndex(moves.length - 1); setExplorerFen(null) }, [moves.length])
  const goToMove  = useCallback((index: number) => { setCurrentMoveIndex(index); setExplorerFen(null) }, [])

  const handlePieceDrop = useCallback((sourceSquare: string, targetSquare: string, piece: string): boolean => {
    if (!isExplorerMode && !piece) return false
    try {
      const { Chess } = require('chess.js') as typeof import('chess.js')
      const chess = new Chess(displayedFen)
      const uci = sourceSquare + targetSquare
      const move = chess.move({ from: sourceSquare, to: targetSquare, promotion: 'q' })
      if (!move) return false
      void uci
      setExplorerFen(chess.fen())
      return true
    } catch {
      return false
    }
  }, [displayedFen, isExplorerMode])

  // Notify parent of ply changes for URL sync.
  // Use a ref so this effect only re-runs when the index changes, not when
  // the callback reference changes (which would create a router.replace → searchParams → re-render loop).
  useEffect(() => {
    onPlyChangeRef.current?.(currentMoveIndex + 1)
  }, [currentMoveIndex]) // eslint-disable-line react-hooks/exhaustive-deps

  // Keyboard navigation
  useEffect(() => {
    function handleKey(e: KeyboardEvent) {
      if (e.key === 'ArrowLeft')  { e.preventDefault(); goPrev() }
      if (e.key === 'ArrowRight') { e.preventDefault(); goNext() }
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [goPrev, goNext])

  // Auto-scroll move list to active move
  useEffect(() => {
    if (currentMoveIndex < 0 || !moveListRef.current) return
    const activeCell = moveListRef.current.querySelector('[data-active="true"]')
    activeCell?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
  }, [currentMoveIndex])

  function copyShareLink() {
    if (!game.share_code) return
    navigator.clipboard.writeText(`${window.location.origin}/g/${game.share_code}`)
    setCopiedShare(true)
    setTimeout(() => setCopiedShare(false), 2000)
  }

  function copyCurrentPosition() {
    if (!game.share_code) return
    const ply = currentMoveIndex + 1
    const suffix = ply > 0 ? `?ply=${ply}` : ''
    navigator.clipboard.writeText(`${window.location.origin}/g/${game.share_code}${suffix}`)
    setCopiedPos(true)
    setTimeout(() => setCopiedPos(false), 2000)
  }

  // Group flat moves array into chess move pairs for display.
  // We iterate by 2s over the flat ordered array — safer than colour-based assumptions.
  const movePairs: Array<{ number: number; white: Move | null; black: Move | null; whiteIndex: number; blackIndex: number }> = []
  for (let i = 0; i < moves.length; i += 2) {
    movePairs.push({
      number:      Math.floor(i / 2) + 1,
      white:       moves[i] ?? null,
      black:       moves[i + 1] ?? null,
      whiteIndex:  i,
      blackIndex:  i + 1,
    })
  }

  const currentMove = currentMoveIndex >= 0 ? moves[currentMoveIndex] ?? null : null

  const btnBase: React.CSSProperties = {
    padding: '6px 14px',
    fontSize: '0.8rem',
    borderRadius: '6px',
    border: '1px solid var(--border)',
    cursor: 'pointer',
    transition: 'all 0.15s',
  }

  return (
    <>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-3xl font-semibold mb-1" style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}>
          {game.white_player} vs {game.black_player}
        </h1>
        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
          {resolvedOpeningName || '—'}{game.eco_code ? ` · ${game.eco_code}` : ''}{game.played_at ? ` · ${game.played_at}` : ''}
          {' · '}{RESULT_LABEL[game.result] ?? game.result}
        </p>
        {game.source_url && (
          <p className="text-sm mt-2">
            <a
              href={game.source_url}
              target="_blank"
              rel="noopener noreferrer"
              style={{ color: 'var(--gold)', textDecoration: 'underline' }}
            >
              View on Chess.com
            </a>
          </p>
        )}
        {openingLabel && (
          <p className="text-sm mt-2" style={{ color: '#93c5fd' }}>
            Opening book: {openingLabel}
          </p>
        )}
      </div>

      {/* Share buttons */}
      {game.share_code && (
        <div className="mb-6 flex gap-2 justify-end">
          <button
            onClick={copyShareLink}
            style={{
              ...btnBase,
              background: copiedShare ? 'rgba(80,200,120,0.15)' : 'var(--surface)',
              color: copiedShare ? 'var(--green)' : 'var(--text-muted)',
            }}
          >
            {copiedShare ? 'Copied!' : 'Copy share link'}
          </button>
          <button
            onClick={copyCurrentPosition}
            style={{
              ...btnBase,
              background: copiedPos ? 'rgba(80,200,120,0.15)' : 'var(--surface)',
              color: copiedPos ? 'var(--green)' : 'var(--text-muted)',
            }}
          >
            {copiedPos ? 'Copied!' : 'Copy current position'}
          </button>
        </div>
      )}

      {/* Analysis status banners */}
      {game.analysis_status === 'pending' && (
        <div className="mb-6 p-4 rounded text-sm flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" style={{ background: 'rgba(201,168,76,0.10)', border: '1px solid rgba(201,168,76,0.25)', color: 'var(--gold)' }}>
          <span>This game has not been analysed yet.</span>
          {onRequestAnalysis && (
            <button
              type="button"
              onClick={onRequestAnalysis}
              style={{ ...btnBase, background: 'rgba(201,168,76,0.18)', color: 'var(--gold)', border: '1px solid rgba(201,168,76,0.45)', whiteSpace: 'nowrap' }}
            >
              Analyse this game
            </button>
          )}
        </div>
      )}
      {(game.analysis_status === 'queued' || game.analysis_status === 'analysing') && (
        <div className="mb-6 p-4 rounded text-sm flex items-center gap-3" style={{ background: 'rgba(201,168,76,0.10)', border: '1px solid rgba(201,168,76,0.25)', color: 'var(--gold)' }}>
          <span className="animate-pulse">●</span>
          {game.analysis_status === 'queued' ? 'Analysis queued — results will appear automatically.' : 'Analysis in progress — results will appear automatically.'}
        </div>
      )}
      {game.analysis_status === 'failed' && (
        <div className="mb-6 p-4 rounded text-sm flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" style={{ background: 'rgba(220,60,60,0.10)', border: '1px solid rgba(220,60,60,0.3)', color: 'var(--red)' }}>
          <span>Analysis failed.</span>
          {onRequestAnalysis && (
            <button
              type="button"
              onClick={onRequestAnalysis}
              style={{ ...btnBase, background: 'rgba(220,60,60,0.12)', color: 'var(--red)', border: '1px solid rgba(220,60,60,0.40)', whiteSpace: 'nowrap' }}
            >
              Retry analysis
            </button>
          )}
        </div>
      )}
      {analyseError && (
        <div className="mb-4 p-3 rounded text-sm" style={{ background: 'rgba(220,60,60,0.08)', border: '1px solid rgba(220,60,60,0.25)', color: 'var(--red)' }}>
          {analyseError}
        </div>
      )}

      {/* Stats */}
      {game.analysis_status === 'analysed' && (
        <>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            <StatCard label="Accuracy"     value={game.accuracy_pct != null ? `${game.accuracy_pct}%` : '—'} color="var(--gold)" />
            <StatCard label="Accuracy (W/B)" value={`${whiteAccuracyEstimate.toFixed(1)} / ${blackAccuracyEstimate.toFixed(1)}`} color="var(--gold)" />
            <StatCard label="Elo (Est, W/B)" value={`${whiteEloEstimate} / ${blackEloEstimate}`} color="#e5e7eb" />
            <StatCard label="Blunders (W/B)" value={`${whiteStats.blunders} / ${blackStats.blunders}`} color="#f87171" />
            <StatCard label="Mistakes (W/B)" value={`${whiteStats.mistakes} / ${blackStats.mistakes}`} color="#fb923c" />
            <StatCard label="Inaccuracies (W/B)" value={`${whiteStats.inaccuracies} / ${blackStats.inaccuracies}`} color="#facc15" />
          </div>
          <StatsLegend />
        </>
      )}

      {/* Main two-column layout */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
        {/* Left: board + controls */}
        <div className="flex flex-col gap-4">
          <div style={{ position: 'relative' }}>
            <ChessBoardViewer
              fen={currentFen}
              lastMove={isExplorerMode ? null : lastMove}
              orientation={boardOrientation}
              allowDragging={true}
              onPieceDrop={handlePieceDrop}
            />
            {isExplorerMode && (
              <div
                style={{
                  position: 'absolute',
                  inset: 0,
                  border: '3px solid var(--gold)',
                  borderRadius: 4,
                  pointerEvents: 'none',
                }}
              />
            )}
          </div>
          {isExplorerMode ? (
            <button
              onClick={() => setExplorerFen(null)}
              style={{
                ...btnBase,
                background: 'rgba(201,168,76,0.12)',
                color: 'var(--gold)',
                border: '1px solid rgba(201,168,76,0.35)',
              }}
            >
              ← Exit explorer
            </button>
          ) : (
            <MoveNavControls
              currentIndex={currentMoveIndex}
              totalMoves={moves.length}
              onFirst={goFirst}
              onPrev={goPrev}
              onNext={goNext}
              onLast={goLast}
              disabled={isPending}
            />
          )}
        </div>

        {/* Right: move list + detail panel */}
        <div className="flex flex-col gap-4">
          <HintModeToggle />
          <div
            className="rounded overflow-hidden"
            style={{ border: '1px solid rgba(232,224,208,0.10)', maxHeight: 'calc(100vh - 360px)', overflowY: 'auto' }}
          >
            {moves.length === 0 ? (
              <p className="px-4 py-6 text-sm" style={{ color: 'var(--text-muted)' }}>
                {isPending ? 'Waiting for analysis…' : 'No moves recorded.'}
              </p>
            ) : (
              <table className="w-full text-sm" style={{ borderCollapse: 'collapse' }}>
                <thead>
                  <tr style={{ background: 'var(--surface)', borderBottom: '1px solid rgba(232,224,208,0.10)' }}>
                    <th className="text-left px-4 py-2 text-xs uppercase tracking-wider w-12" style={{ color: 'var(--text-muted)' }}>#</th>
                    <th className="text-left px-4 py-2 text-xs uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>White</th>
                    <th className="text-left px-4 py-2 text-xs uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>Black</th>
                  </tr>
                </thead>
                <tbody ref={moveListRef}>
                  {movePairs.map(({ number, white, black, whiteIndex, blackIndex }) => (
                    <tr
                      key={number}
                      style={{ borderBottom: '1px solid rgba(232,224,208,0.06)' }}
                    >
                      <td className="px-4 py-1 text-xs" style={{ color: 'var(--text-faint)', fontFamily: 'var(--font-dm-mono)' }}>
                        {number}.
                      </td>
                      <td className="px-2 py-1">
                        <MoveCell
                          move={white}
                          isActive={currentMoveIndex === whiteIndex}
                          isBookMove={Boolean(white && hasKnownOpening && white.move_number <= openingBookPlyLimit)}
                          onClick={() => goToMove(whiteIndex)}
                        />
                      </td>
                      <td className="px-2 py-1">
                        <MoveCell
                          move={black}
                          isActive={currentMoveIndex === blackIndex && black != null}
                          isBookMove={Boolean(black && hasKnownOpening && black.move_number <= openingBookPlyLimit)}
                          onClick={() => { if (black) goToMove(blackIndex) }}
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          {game.analysis_status === 'analysed' ? (
            <>
              <MoveDetailPanel
                move={currentMove}
                isBookMove={Boolean(currentMove && hasKnownOpening && currentMove.move_number <= openingBookPlyLimit)}
                openingLabel={openingLabel}
              />
              <MoveExplorerPanel
                fen={isExplorerMode ? currentFen : explorerBaseFen}
                onTryMove={(newFen) => setExplorerFen(newFen)}
              />
              {(game.key_moments?.length ?? 0) > 0 && (
                <KeyMomentsPanel
                  keyMoments={game.key_moments!}
                  onJumpToMove={(moveId) => {
                    const idx = moves.findIndex(m => m.id === moveId)
                    if (idx >= 0) goToMove(idx)
                  }}
                />
              )}
            </>
          ) : (
            <div
              className="rounded p-5 text-sm text-center"
              style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)', color: 'var(--text-muted)' }}
            >
              Analyse this game to unlock coaching insights — move evaluations, key moments, and position explorer.
            </div>
          )}
        </div>
      </div>
    </>
  )
}

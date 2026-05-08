'use client'

import dynamic from 'next/dynamic'
import { useState, useCallback, useEffect, useRef } from 'react'
import MoveNavControls from '@/components/MoveNavControls'
import MoveDetailPanel from '@/components/MoveDetailPanel'

const ChessBoardViewer = dynamic(() => import('@/components/ChessBoardViewer'), {
  ssr: false,
  loading: () => (
    <div
      className="w-full animate-pulse"
      style={{ aspectRatio: '1 / 1', background: 'var(--surface)', borderRadius: 4 }}
    />
  ),
})

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
  analysis_status: 'pending' | 'running' | 'complete' | 'failed'
  accuracy_pct: string | null
  blunder_count: number | null
  mistake_count: number | null
  inaccuracy_count: number | null
  user_colour: 'white' | 'black' | null
  share_code: string | null
  moves: Move[]
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

function MoveCell({
  move,
  isActive,
  onClick,
}: {
  move: Move | null
  isActive: boolean
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
}

export default function GameAnalysisView({ game }: Props) {
  const moves = game.moves
  const [currentMoveIndex, setCurrentMoveIndex] = useState<number>(-1)
  const moveListRef = useRef<HTMLTableSectionElement>(null)

  const currentFen =
    currentMoveIndex === -1
      ? START_FEN
      : (moves[currentMoveIndex]?.fen_after ?? START_FEN)

  // UCI is 4–5 chars (e.g., "g1f3" or "e7e8q"). We use only the first 4 chars —
  // the promotion piece char (5th) doesn't affect square highlighting.
  const lastMoveUci = currentMoveIndex >= 0 ? moves[currentMoveIndex]?.uci ?? null : null
  const lastMove = lastMoveUci
    ? { from: lastMoveUci.slice(0, 2) as string, to: lastMoveUci.slice(2, 4) as string }
    : null

  const boardOrientation = game.user_colour === 'black' ? 'black' : 'white'
  const isPending = game.analysis_status === 'pending' || game.analysis_status === 'running'

  const goFirst   = useCallback(() => setCurrentMoveIndex(-1), [])
  const goPrev    = useCallback(() => setCurrentMoveIndex(i => Math.max(-1, i - 1)), [])
  const goNext    = useCallback(() => setCurrentMoveIndex(i => Math.min(moves.length - 1, i + 1)), [moves.length])
  const goLast    = useCallback(() => setCurrentMoveIndex(moves.length - 1), [moves.length])
  const goToMove  = useCallback((index: number) => setCurrentMoveIndex(index), [])

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

  return (
    <>
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-semibold mb-1" style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}>
          {game.white_player} vs {game.black_player}
        </h1>
        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
          {game.opening_name || '—'}{game.eco_code ? ` · ${game.eco_code}` : ''}{game.played_at ? ` · ${game.played_at}` : ''}
          {' · '}{RESULT_LABEL[game.result] ?? game.result}
        </p>
      </div>

      {/* Analysis status banners */}
      {isPending && (
        <div className="mb-6 p-4 rounded text-sm flex items-center gap-3" style={{ background: 'rgba(201,168,76,0.10)', border: '1px solid rgba(201,168,76,0.25)', color: 'var(--gold)' }}>
          <span className="animate-pulse">●</span>
          Analysis {game.analysis_status === 'running' ? 'in progress' : 'queued'} — results will appear automatically.
        </div>
      )}
      {game.analysis_status === 'failed' && (
        <div className="mb-6 p-4 rounded text-sm" style={{ background: 'rgba(220,60,60,0.10)', border: '1px solid rgba(220,60,60,0.3)', color: 'var(--red)' }}>
          Analysis failed. You can re-import the game to try again.
        </div>
      )}

      {/* Stats */}
      {game.analysis_status === 'complete' && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
          <StatCard label="Accuracy"     value={game.accuracy_pct != null ? `${game.accuracy_pct}%` : '—'} color="var(--gold)" />
          <StatCard label="Blunders"     value={game.blunder_count ?? '—'}    color="#f87171" />
          <StatCard label="Mistakes"     value={game.mistake_count ?? '—'}    color="#fb923c" />
          <StatCard label="Inaccuracies" value={game.inaccuracy_count ?? '—'} color="#facc15" />
        </div>
      )}

      {/* Main two-column layout */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
        {/* Left: board + controls */}
        <div className="flex flex-col gap-4">
          <ChessBoardViewer
            fen={currentFen}
            lastMove={lastMove}
            orientation={boardOrientation}
          />
          <MoveNavControls
            currentIndex={currentMoveIndex}
            totalMoves={moves.length}
            onFirst={goFirst}
            onPrev={goPrev}
            onNext={goNext}
            onLast={goLast}
            disabled={isPending}
          />
        </div>

        {/* Right: move list + detail panel */}
        <div className="flex flex-col gap-4">
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
                          onClick={() => goToMove(whiteIndex)}
                        />
                      </td>
                      <td className="px-2 py-1">
                        <MoveCell
                          move={black}
                          isActive={currentMoveIndex === blackIndex && black != null}
                          onClick={() => { if (black) goToMove(blackIndex) }}
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          <MoveDetailPanel move={currentMove} />
        </div>
      </div>
    </>
  )
}

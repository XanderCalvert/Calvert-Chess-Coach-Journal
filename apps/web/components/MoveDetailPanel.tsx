'use client'

import { CLASSIFICATION_STYLES, type Move } from '@/components/GameAnalysisView'

interface MoveDetailPanelProps {
  move: Move | null
  isBookMove?: boolean
  openingLabel?: string | null
}

export default function MoveDetailPanel({ move, isBookMove = false, openingLabel = null }: MoveDetailPanelProps) {
  const style = {
    background: 'var(--surface)',
    border: '1px solid rgba(232,224,208,0.10)',
    borderRadius: 6,
    padding: '16px',
    minHeight: 80,
  }

  if (!move) {
    return (
      <div style={style}>
        <p className="text-sm" style={{ color: 'var(--text-faint)' }}>Select a move to see details.</p>
      </div>
    )
  }

  const cls = move.classification ? CLASSIFICATION_STYLES[move.classification] : null

  return (
    <div style={style} className="flex flex-col gap-3">
      <div className="flex items-center gap-3">
        <span style={{ fontFamily: 'var(--font-dm-mono)', color: 'var(--text-muted)', fontSize: 13 }}>
          {Math.ceil(move.move_number / 2)}.{move.colour === 'black' ? '..' : ''}
        </span>
        <span style={{ fontFamily: 'var(--font-dm-mono)', color: 'var(--text)', fontSize: 18, fontWeight: 600 }}>
          {move.san}
        </span>
        {isBookMove && (
          <span
            className="text-xs px-2 py-0.5 rounded font-medium"
            style={{ color: '#93c5fd', background: 'rgba(147,197,253,0.15)' }}
          >
            Book
          </span>
        )}
        {cls && (
          <span
            className="text-xs px-2 py-0.5 rounded font-medium"
            style={{ color: cls.color, background: cls.bg }}
          >
            {cls.label}
          </span>
        )}
      </div>

      <div className="flex gap-4 text-sm" style={{ color: 'var(--text-muted)', fontFamily: 'var(--font-dm-mono)' }}>
        {move.cp_loss != null && (
          <span>Loss: <span style={{ color: move.cp_loss > 100 ? '#f87171' : move.cp_loss > 50 ? '#fb923c' : 'var(--text)' }}>−{move.cp_loss} cp</span></span>
        )}
        {move.cp_score != null && (
          <span>Eval: <span style={{ color: 'var(--text)' }}>{move.cp_score > 0 ? '+' : ''}{move.cp_score} cp</span></span>
        )}
      </div>

      {isBookMove && openingLabel && (
        <p className="text-xs" style={{ color: 'var(--text-faint)' }}>
          Opening book phase: {openingLabel}
        </p>
      )}

      {/* AI coaching explanation will render here — Phase 5 */}
    </div>
  )
}

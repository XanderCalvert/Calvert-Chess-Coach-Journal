'use client'

import { CLASSIFICATION_STYLES, type KeyMoment } from '@/components/GameAnalysisView'

interface KeyMomentsPanelProps {
  keyMoments: KeyMoment[]
  onJumpToMove: (moveId: string) => void
}

const PHASE_LABEL: Record<string, string> = {
  opening:    'Opening',
  middlegame: 'Middlegame',
  endgame:    'Endgame',
}

function cpLossColor(cpLoss: number): string {
  if (cpLoss > 300) return '#f87171'
  if (cpLoss > 140) return '#fb923c'
  return '#facc15'
}

function MoveNotation({ moveNumber, colour, san }: { moveNumber: number; colour: 'white' | 'black'; san: string }) {
  const fullMove = Math.ceil(moveNumber / 2)
  const prefix = colour === 'black' ? `${fullMove}...` : `${fullMove}.`
  return (
    <span style={{ fontFamily: 'var(--font-dm-mono)', color: 'var(--text)', fontSize: 16, fontWeight: 600 }}>
      {prefix}{san}
    </span>
  )
}

export default function KeyMomentsPanel({ keyMoments, onJumpToMove }: KeyMomentsPanelProps) {
  return (
    <div className="flex flex-col gap-3">
      <h3 className="text-xs uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
        Key Moments
      </h3>
      {keyMoments.map((km) => {
        const cls = km.classification ? CLASSIFICATION_STYLES[km.classification] : null
        const showBest = km.best_move_san && km.best_move_san !== km.san

        return (
          <div
            key={km.rank}
            className="rounded p-4 flex flex-col gap-3"
            style={{ background: 'var(--surface)', border: '1px solid rgba(232,224,208,0.10)' }}
          >
            {/* Top row: rank + phase + classification */}
            <div className="flex items-center gap-2 flex-wrap">
              <span
                className="text-xs font-semibold px-2 py-0.5 rounded"
                style={{ color: 'var(--gold)', background: 'rgba(201,168,76,0.15)', fontFamily: 'var(--font-dm-mono)' }}
              >
                #{km.rank}
              </span>
              <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                {PHASE_LABEL[km.game_phase] ?? km.game_phase}
              </span>
              {cls && (
                <span
                  className="text-xs px-2 py-0.5 rounded font-medium"
                  style={{ color: cls.color, background: cls.bg }}
                >
                  {cls.label}
                </span>
              )}
              <span
                className="text-xs"
                style={{ color: cpLossColor(km.cp_loss), fontFamily: 'var(--font-dm-mono)', marginLeft: 'auto' }}
              >
                −{km.cp_loss} cp
              </span>
            </div>

            {/* Move notation */}
            <MoveNotation moveNumber={km.move_number} colour={km.colour} san={km.san} />

            {/* Played vs best */}
            {showBest && (
              <div className="text-xs flex gap-2 items-center" style={{ fontFamily: 'var(--font-dm-mono)' }}>
                <span style={{ color: 'var(--text-muted)' }}>Played:</span>
                <span style={{ color: '#f87171' }}>{km.san}</span>
                <span style={{ color: 'var(--text-faint)' }}>→</span>
                <span style={{ color: 'var(--text-muted)' }}>Best:</span>
                <span style={{ color: '#4ade80' }}>{km.best_move_san}</span>
              </div>
            )}

            {/* Risk note / explanation */}
            {(km.explanation_text || km.risk_note) && (
              <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                {km.explanation_text ?? km.risk_note}
              </p>
            )}

            {/* Jump button */}
            <button
              onClick={() => onJumpToMove(km.move_id)}
              className="text-xs self-start px-3 py-1 rounded transition-colors"
              style={{
                background: 'rgba(201,168,76,0.10)',
                color: 'var(--gold)',
                border: '1px solid rgba(201,168,76,0.25)',
                cursor: 'pointer',
              }}
            >
              Go to position
            </button>
          </div>
        )
      })}
    </div>
  )
}

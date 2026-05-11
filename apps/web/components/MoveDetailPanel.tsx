'use client'

import { CLASSIFICATION_STYLES, type Move, type ThreatAwareness } from '@/components/GameAnalysisView'
import { useHintMode } from '@/contexts/HintModeContext'

interface MoveDetailPanelProps {
  move: Move | null
  isBookMove?: boolean
  openingLabel?: string | null
}

const THEME_LABELS: Record<string, string> = {
  development:       'Development',
  center_control:    'Center Control',
  material:          'Material',
  king_safety:       'King Safety',
  activity:          'Activity',
  engine_prefers_capture: 'Engine Prefers Capture',
  forced_mate_present:    'Forced Mate',
}

const RESPONSE_STYLES: Record<string, { color: string; icon: string }> = {
  addressed:     { color: '#4ade80', icon: '✓' },
  not_addressed: { color: '#facc15', icon: '!' },
  worsened:      { color: '#f87171', icon: '✕' },
  unknown:       { color: 'var(--text-muted)', icon: '?' },
  none:          { color: 'var(--text-muted)', icon: '' },
}

function ThemeTag({ theme }: { theme: string }) {
  const label = THEME_LABELS[theme] ?? theme
  return (
    <span
      className="text-xs px-2 py-0.5 rounded font-medium"
      style={{ color: 'var(--text-muted)', background: 'rgba(232,224,208,0.08)', border: '1px solid rgba(232,224,208,0.12)' }}
    >
      {label}
    </span>
  )
}

function ThreatCallout({ awareness }: { awareness: ThreatAwareness }) {
  if (!awareness.threats_before.length) return null

  const responseStyle = RESPONSE_STYLES[awareness.response] ?? RESPONSE_STYLES.unknown
  const threatList = awareness.threats_before
    .map(t => t.replace(/_/g, ' '))
    .join(', ')

  return (
    <div
      className="rounded p-3 flex flex-col gap-1"
      style={{ background: 'rgba(232,224,208,0.05)', border: '1px solid rgba(232,224,208,0.10)' }}
    >
      <p className="text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
        Threat detected: <span style={{ color: 'var(--text)' }}>{threatList}</span>
      </p>
      {awareness.response !== 'none' && (
        <p className="text-xs" style={{ color: responseStyle.color }}>
          {responseStyle.icon && <span className="mr-1">{responseStyle.icon}</span>}
          Response: {awareness.response.replace('_', ' ')}
        </p>
      )}
    </div>
  )
}

export default function MoveDetailPanel({ move, isBookMove = false, openingLabel = null }: MoveDetailPanelProps) {
  const { hintMode } = useHintMode()

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

      {/* Eval metrics — hidden in training mode */}
      {hintMode !== 'training' && (
        <div className="flex gap-4 text-sm" style={{ color: 'var(--text-muted)', fontFamily: 'var(--font-dm-mono)' }}>
          {move.cp_loss != null && (
            <span>Loss: <span style={{ color: move.cp_loss > 100 ? '#f87171' : move.cp_loss > 50 ? '#fb923c' : 'var(--text)' }}>−{move.cp_loss} cp</span></span>
          )}
          {move.cp_score != null && (
            <span>Eval: <span style={{ color: 'var(--text)' }}>{move.cp_score > 0 ? '+' : ''}{move.cp_score} cp</span></span>
          )}
        </div>
      )}

      {isBookMove && openingLabel && (
        <p className="text-xs" style={{ color: 'var(--text-faint)' }}>
          Opening book phase: {openingLabel}
        </p>
      )}

      {/* Best move suggestion — hidden in training mode, only shown when a better move existed */}
      {hintMode !== 'training' && move.best_move_san && move.cp_loss != null && move.cp_loss >= 20 && (
        <div
          className="rounded p-3 flex flex-col gap-1.5"
          style={{ background: 'rgba(147,197,253,0.06)', border: '1px solid rgba(147,197,253,0.15)' }}
        >
          <p className="text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
            Best move:{' '}
            <span style={{ color: '#93c5fd', fontFamily: 'var(--font-dm-mono)', fontSize: 14 }}>
              {move.best_move_san}
            </span>
          </p>
          {move.best_line && move.best_line.length > 1 && (
            <p className="text-xs" style={{ color: 'var(--text-faint)', fontFamily: 'var(--font-dm-mono)', lineHeight: 1.6 }}>
              {move.best_line.slice(0, 6).join(' ')}
              {move.best_line.length > 6 ? ' …' : ''}
            </p>
          )}
        </div>
      )}

      {/* Coaching sections — guided and full analysis only */}
      {hintMode !== 'training' && (
        <>
          {/* Theme tags */}
          {move.themes && move.themes.length > 0 && (
            <div className="flex gap-1 flex-wrap">
              {move.themes.map(theme => (
                <ThemeTag key={theme} theme={theme} />
              ))}
            </div>
          )}

          {/* Threat callout — medium/high confidence only to avoid noise */}
          {move.threat_awareness
            && move.threat_awareness.confidence !== 'low'
            && move.threat_awareness.threats_before.length > 0
            && (
              <ThreatCallout awareness={move.threat_awareness} />
            )}
        </>
      )}

      {/* Risk note — full analysis only */}
      {hintMode === 'full' && move.risk_note && (
        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
          {move.risk_note}
        </p>
      )}

      {/* AI coaching explanation will render here — Phase 5 */}
    </div>
  )
}

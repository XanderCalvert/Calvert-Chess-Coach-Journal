'use client'

interface MoveNavControlsProps {
  currentIndex: number
  totalMoves: number
  onFirst: () => void
  onPrev: () => void
  onNext: () => void
  onLast: () => void
  disabled?: boolean
}

const btnStyle = (active: boolean): React.CSSProperties => ({
  background: 'var(--surface)',
  border: '1px solid rgba(232,224,208,0.12)',
  color: active ? 'var(--text)' : 'var(--text-faint)',
  borderRadius: 4,
  padding: '6px 14px',
  fontSize: 14,
  fontFamily: 'var(--font-dm-mono)',
  cursor: active ? 'pointer' : 'not-allowed',
  opacity: active ? 1 : 0.4,
  transition: 'opacity 0.15s',
})

export default function MoveNavControls({
  currentIndex,
  totalMoves,
  onFirst,
  onPrev,
  onNext,
  onLast,
  disabled = false,
}: MoveNavControlsProps) {
  const atStart = currentIndex === -1
  const atEnd   = currentIndex === totalMoves - 1 || totalMoves === 0

  return (
    <div className="flex gap-2 justify-center">
      <button
        onClick={onFirst}
        disabled={disabled || atStart}
        style={btnStyle(!disabled && !atStart)}
        aria-label="First move"
      >
        {'|<'}
      </button>
      <button
        onClick={onPrev}
        disabled={disabled || atStart}
        style={btnStyle(!disabled && !atStart)}
        aria-label="Previous move"
      >
        {'<'}
      </button>
      <button
        onClick={onNext}
        disabled={disabled || atEnd}
        style={btnStyle(!disabled && !atEnd)}
        aria-label="Next move"
      >
        {'>'}
      </button>
      <button
        onClick={onLast}
        disabled={disabled || atEnd}
        style={btnStyle(!disabled && !atEnd)}
        aria-label="Last move"
      >
        {'>|'}
      </button>
    </div>
  )
}

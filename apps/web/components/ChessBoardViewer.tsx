'use client'

// Loaded via dynamic() in consumers — do NOT import react-chessboard anywhere else.
import { Chessboard } from 'react-chessboard'

interface ChessBoardViewerProps {
  fen: string
  lastMove: { from: string; to: string } | null
  orientation?: 'white' | 'black'
  allowDragging?: boolean
  onPieceDrop?: (sourceSquare: string, targetSquare: string, piece: string) => boolean
}

export default function ChessBoardViewer({
  fen,
  lastMove,
  orientation = 'white',
  allowDragging = false,
  onPieceDrop,
}: ChessBoardViewerProps) {
  const squareStyles: Record<string, React.CSSProperties> = {}
  if (lastMove) {
    squareStyles[lastMove.from] = { background: 'rgba(201,168,76,0.35)' }
    squareStyles[lastMove.to]   = { background: 'rgba(201,168,76,0.55)' }
  }

  const handlePieceDrop = onPieceDrop
    ? ({ sourceSquare, targetSquare, piece }: { sourceSquare: string; targetSquare: string | null; piece: unknown }) =>
        targetSquare ? onPieceDrop(sourceSquare, targetSquare, String(piece)) : false
    : undefined

  return (
    <Chessboard
      options={{
        position:         fen,
        boardOrientation: orientation,
        squareStyles,
        allowDragging,
        onPieceDrop: handlePieceDrop,
        darkSquareStyle:  { backgroundColor: '#2a231a' },
        lightSquareStyle: { backgroundColor: '#4a3d2a' },
      }}
    />
  )
}

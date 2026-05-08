'use client'

// Loaded via dynamic() in consumers — do NOT import react-chessboard anywhere else.
import { Chessboard } from 'react-chessboard'

interface ChessBoardViewerProps {
  fen: string
  lastMove: { from: string; to: string } | null
  orientation?: 'white' | 'black'
}

export default function ChessBoardViewer({
  fen,
  lastMove,
  orientation = 'white',
}: ChessBoardViewerProps) {
  const squareStyles: Record<string, React.CSSProperties> = {}
  if (lastMove) {
    squareStyles[lastMove.from] = { background: 'rgba(201,168,76,0.35)' }
    squareStyles[lastMove.to]   = { background: 'rgba(201,168,76,0.55)' }
  }

  return (
    <Chessboard
      options={{
        position:        fen,
        boardOrientation: orientation,
        squareStyles,
        allowDragging:   false,
        darkSquareStyle:  { backgroundColor: '#2a231a' },
        lightSquareStyle: { backgroundColor: '#4a3d2a' },
      }}
    />
  )
}

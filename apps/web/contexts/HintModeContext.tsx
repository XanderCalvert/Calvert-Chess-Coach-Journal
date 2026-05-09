'use client'

import { createContext, useContext, useState } from 'react'

export type HintMode = 'training' | 'guided' | 'full'

interface HintModeContextValue {
  hintMode: HintMode
  setHintMode: (mode: HintMode) => void
}

const HintModeContext = createContext<HintModeContextValue>({
  hintMode: 'full',
  setHintMode: () => {},
})

export function HintModeProvider({ children }: { children: React.ReactNode }) {
  const [hintMode, setHintMode] = useState<HintMode>('full')
  return (
    <HintModeContext.Provider value={{ hintMode, setHintMode }}>
      {children}
    </HintModeContext.Provider>
  )
}

export function useHintMode() {
  return useContext(HintModeContext)
}

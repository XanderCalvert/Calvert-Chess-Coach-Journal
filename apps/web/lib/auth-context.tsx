'use client'

import { createContext, useContext, useState } from 'react'

export interface AuthUser {
  id: string
  email: string
  display_name: string | null
  has_connected_accounts: boolean
}

interface AuthContextValue {
  user: AuthUser | null
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue>({
  user: null,
  logout: async () => {},
})

export function AuthProvider({
  initialUser,
  children,
}: {
  initialUser: AuthUser | null
  children: React.ReactNode
}) {
  const [user] = useState<AuthUser | null>(initialUser)

  async function logout() {
    await fetch('/api/auth/logout', { method: 'POST' })
    window.location.href = '/login'
  }

  return <AuthContext.Provider value={{ user, logout }}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  return useContext(AuthContext)
}

'use client'

import Link from 'next/link'
import { useAuth } from '@/lib/auth-context'
import NavSignOut from './NavSignOut'

export default function Nav() {
  const { user } = useAuth()

  return (
    <nav
      aria-label="Main navigation"
      className="flex justify-between items-center px-8 py-5 border-b sticky top-0 z-50"
      style={{
        borderColor: 'rgba(232,224,208,0.12)',
        background: 'rgba(15,13,11,0.92)',
        backdropFilter: 'blur(8px)',
      }}
    >
      <Link
        href={user ? '/games' : '/'}
        className="flex items-center gap-2 text-[17px] font-semibold tracking-[0.01em] no-underline"
        style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}
      >
        <span aria-hidden="true">♟</span>
        Chess Coach Journal
      </Link>

      <ul className="flex gap-6 items-center list-none">
        {user ? (
          <>
            <li>
              <Link href="/games" className="nav-link text-[13px] tracking-[0.03em] no-underline">
                My Games
              </Link>
            </li>
            <li>
              <Link href="/settings" className="nav-link text-[13px] tracking-[0.03em] no-underline">
                Settings
              </Link>
            </li>
            <li>
              <NavSignOut />
            </li>
          </>
        ) : (
          <>
            <li>
              <Link href="/login" className="nav-link text-[13px] tracking-[0.03em] no-underline">
                Sign in
              </Link>
            </li>
            <li>
              <Link
                href="/register"
                className="text-[13px] font-medium tracking-[0.02em] no-underline px-[18px] py-2 rounded-[4px] transition-colors duration-200"
                style={{ background: 'var(--gold)', color: 'var(--bg)' }}
              >
                Get started
              </Link>
            </li>
          </>
        )}
      </ul>
    </nav>
  )
}

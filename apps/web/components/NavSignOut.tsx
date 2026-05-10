'use client'

export default function NavSignOut() {
  async function handleSignOut() {
    await fetch('/api/auth/logout', { method: 'POST' })
    window.location.href = '/login'
  }

  return (
    <button
      onClick={handleSignOut}
      className="nav-link text-[13px] tracking-[0.03em]"
    >
      Sign out
    </button>
  )
}

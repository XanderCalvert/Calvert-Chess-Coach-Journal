import { getRequiredUser } from '@/lib/server-auth'
import SettingsClient from './SettingsClient'
import Nav from '@/components/Nav'

export default async function SettingsPage() {
  const user = await getRequiredUser()

  return (
    <>
      <Nav />
      <main className="flex-1 w-full max-w-2xl mx-auto px-6 py-10 flex flex-col gap-10">
        <header className="flex flex-col gap-1">
          <h1
            className="text-3xl font-semibold"
            style={{ fontFamily: 'var(--font-playfair)', color: 'var(--text)' }}
          >
            Settings
          </h1>
          <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
            Your account and connected chess sites.
          </p>
        </header>
        <SettingsClient user={user} />
      </main>
    </>
  )
}

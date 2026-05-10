import { getRequiredUser } from '@/lib/server-auth'
import SettingsClient from './SettingsClient'
import Nav from '@/components/Nav'

export default async function SettingsPage() {
  const user = await getRequiredUser()

  return (
    <>
      <Nav />
      <main className="max-w-xl mx-auto px-4 py-12 flex flex-col gap-10">
        <h1 className="text-2xl font-semibold">Settings</h1>
        <SettingsClient user={user} />
      </main>
    </>
  )
}

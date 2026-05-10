import { getRequiredUser } from '@/lib/server-auth'

export default async function GamesLayout({ children }: { children: React.ReactNode }) {
  await getRequiredUser()
  return <>{children}</>
}

import { getRequiredUser } from '@/lib/server-auth'

export default async function ImportLayout({ children }: { children: React.ReactNode }) {
  await getRequiredUser()
  return <>{children}</>
}

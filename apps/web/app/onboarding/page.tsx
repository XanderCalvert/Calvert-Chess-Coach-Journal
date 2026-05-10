import { redirect } from 'next/navigation'
import { getAuthenticatedUser } from '@/lib/server-auth'
import OnboardingForm from './OnboardingForm'

export default async function OnboardingPage() {
  const user = await getAuthenticatedUser()
  if (!user) redirect('/login')
  if (user.has_connected_accounts) redirect('/games')

  return (
    <main className="flex justify-center pt-16 px-4">
      <div className="w-full max-w-sm flex flex-col gap-6">
        <div className="flex flex-col gap-1">
          <h1 className="text-2xl font-semibold">Connect your chess account</h1>
          <p className="text-sm text-gray-600">
            Enter your Chess.com or Lichess username to get started. Your games and progress will be imported automatically.
          </p>
          <p className="text-xs text-gray-500 mt-1">
            Only connect chess accounts you own or have permission to analyse.
          </p>
        </div>
        <OnboardingForm />
      </div>
    </main>
  )
}

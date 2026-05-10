'use client'

import { useState, FormEvent } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'

export default function RegisterPage() {
  const router = useRouter()

  const [displayName, setDisplayName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [generalError, setGeneralError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setErrors({})
    setGeneralError(null)
    setLoading(true)

    try {
      const res = await fetch('/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          display_name: displayName,
          email,
          password,
          password_confirmation: passwordConfirmation,
        }),
      })

      if (!res.ok) {
        const data = await res.json().catch(() => ({}))
        if (res.status === 422 && data.errors) {
          setErrors(data.errors as Record<string, string[]>)
        } else {
          setGeneralError((data as { message?: string }).message ?? 'Registration failed.')
        }
        return
      }

      const { user } = await res.json()
      router.push(user?.has_connected_accounts ? '/games' : '/onboarding')
    } catch {
      setGeneralError('An unexpected error occurred. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  function fieldError(field: string) {
    return errors[field]?.[0] ?? null
  }

  return (
    <main className="flex justify-center pt-16 px-4">
      <form onSubmit={handleSubmit} className="w-full max-w-sm flex flex-col gap-4">
        <h1 className="text-2xl font-semibold">Create account</h1>

        {generalError && <p className="text-red-600 text-sm">{generalError}</p>}

        <div className="flex flex-col gap-1">
          <label htmlFor="display_name" className="text-sm font-medium">
            Name
          </label>
          <input
            id="display_name"
            type="text"
            value={displayName}
            onChange={e => setDisplayName(e.target.value)}
            required
            autoComplete="name"
            className="border rounded px-3 py-2 text-sm"
          />
          {fieldError('display_name') && (
            <p className="text-red-600 text-xs">{fieldError('display_name')}</p>
          )}
        </div>

        <div className="flex flex-col gap-1">
          <label htmlFor="email" className="text-sm font-medium">
            Email
          </label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={e => setEmail(e.target.value)}
            required
            autoComplete="email"
            className="border rounded px-3 py-2 text-sm"
          />
          {fieldError('email') && (
            <p className="text-red-600 text-xs">{fieldError('email')}</p>
          )}
        </div>

        <div className="flex flex-col gap-1">
          <label htmlFor="password" className="text-sm font-medium">
            Password
          </label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={e => setPassword(e.target.value)}
            required
            autoComplete="new-password"
            minLength={8}
            className="border rounded px-3 py-2 text-sm"
          />
          {fieldError('password') && (
            <p className="text-red-600 text-xs">{fieldError('password')}</p>
          )}
        </div>

        <div className="flex flex-col gap-1">
          <label htmlFor="password_confirmation" className="text-sm font-medium">
            Confirm password
          </label>
          <input
            id="password_confirmation"
            type="password"
            value={passwordConfirmation}
            onChange={e => setPasswordConfirmation(e.target.value)}
            required
            autoComplete="new-password"
            className="border rounded px-3 py-2 text-sm"
          />
        </div>

        <button
          type="submit"
          disabled={loading}
          className="bg-black text-white rounded px-4 py-2 text-sm font-medium disabled:opacity-50"
        >
          {loading ? 'Creating account…' : 'Create account'}
        </button>

        <p className="text-sm text-center text-gray-600">
          Already have an account?{' '}
          <Link href="/login" className="underline">
            Sign in
          </Link>
        </p>
      </form>
    </main>
  )
}

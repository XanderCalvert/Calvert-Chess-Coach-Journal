import { NextRequest, NextResponse } from 'next/server'

const PROTECTED_PREFIXES = ['/games', '/import', '/onboarding', '/settings']

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl

  const isProtected = PROTECTED_PREFIXES.some(
    prefix => pathname === prefix || pathname.startsWith(prefix + '/'),
  )

  if (!isProtected) return NextResponse.next()

  const token = request.cookies.get('chess_token')?.value
  if (token) return NextResponse.next()

  const loginUrl = new URL('/login', request.url)
  if (!pathname.includes('://')) {
    loginUrl.searchParams.set('return_to', pathname)
  }
  return NextResponse.redirect(loginUrl)
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico|api/).*)'],
}

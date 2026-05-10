import type { Metadata } from "next";
import localFont from "next/font/local";
import { cookies } from "next/headers";
import { AuthProvider, AuthUser } from "@/lib/auth-context";
import "./globals.css";

const playfair = localFont({
  src: [
    { path: "../public/fonts/PlayfairDisplay-Regular.woff2", weight: "400", style: "normal" },
    { path: "../public/fonts/PlayfairDisplay-SemiBold.woff2", weight: "600", style: "normal" },
    { path: "../public/fonts/PlayfairDisplay-Italic.woff2", weight: "400", style: "italic" },
  ],
  variable: "--font-playfair",
  display: "swap",
});

const dmSans = localFont({
  src: [
    { path: "../public/fonts/DMSans-Latin.woff2", weight: "300", style: "normal" },
    { path: "../public/fonts/DMSans-Latin.woff2", weight: "400", style: "normal" },
    { path: "../public/fonts/DMSans-Latin.woff2", weight: "500", style: "normal" },
  ],
  variable: "--font-dm-sans",
  display: "swap",
});

const dmMono = localFont({
  src: [
    { path: "../public/fonts/DMMono-Regular.woff2", weight: "400", style: "normal" },
    { path: "../public/fonts/DMMono-Medium.woff2", weight: "500", style: "normal" },
  ],
  variable: "--font-dm-mono",
  display: "swap",
});

export const metadata: Metadata = {
  title: "Chess Coach Journal",
  description: "Post-game chess analysis, explanations, and improvement tracking.",
};

async function getInitialUser(): Promise<AuthUser | null> {
  const cookieStore = await cookies()
  const token = cookieStore.get('chess_token')?.value
  if (!token) return null

  const laravelUrl = process.env.LARAVEL_API_URL
  if (!laravelUrl) return null

  try {
    const res = await fetch(`${laravelUrl}/api/v1/auth/me`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    })
    if (!res.ok) return null
    return res.json()
  } catch {
    return null
  }
}

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const user = await getInitialUser()

  return (
    <html
      lang="en"
      className={`${playfair.variable} ${dmSans.variable} ${dmMono.variable} h-full antialiased`}
      suppressHydrationWarning
    >
      <body className="min-h-full flex flex-col">
        <AuthProvider initialUser={user}>{children}</AuthProvider>
      </body>
    </html>
  );
}

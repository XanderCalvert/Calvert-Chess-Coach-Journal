import type { Metadata } from "next";
import localFont from "next/font/local";
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

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      className={`${playfair.variable} ${dmSans.variable} ${dmMono.variable} h-full antialiased`}
      suppressHydrationWarning
    >
      <body className="min-h-full flex flex-col">{children}</body>
    </html>
  );
}

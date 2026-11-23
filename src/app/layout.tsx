import type { Metadata, Viewport } from "next";
import "./globals.css";
import { AccessibilityProvider } from "@/components/AccessibilityProvider";
import { AccessibilityPanel } from "@/components/AccessibilityPanel";

const siteUrl = process.env.NEXT_PUBLIC_APP_URL || "https://purplefestivalgujarat.com";

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: {
    default: "Purple Festival Gujarat 2027 | Inclusion, celebrated.",
    template: "%s | Purple Festival Gujarat 2027",
  },
  description:
    "Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara, Gujarat) — India's landmark festival of inclusion, human dignity, assistive technology, Paralympic sport, and universal design across all 21 Benchmark Disabilities (RPwD Act 2016).",
  applicationName: "Purple Festival Gujarat 2027",
  authors: [
    { name: "Brotherhood" },
    { name: "Global Inclusive Education Network (GIEN)" },
  ],
  creator: "Purple Festival Gujarat Secretariat",
  publisher: "Purple Festival Gujarat 2027",
  category: "Festival & Disability Inclusion",
  keywords: [
    "Purple Festival Gujarat 2027",
    "PurpleFest Vadodara",
    "Disability Inclusion India",
    "Accessibility India",
    "RPwD Act 2016",
    "21 Benchmark Disabilities",
    "Vadodara Festival 2027",
    "Para Sports Championships",
    "Assistive Technology Expo",
    "Inclusive Education NEP 2020",
    "Viksit Bharat 2047",
    "Indian Sign Language",
  ],
  alternates: {
    canonical: "/",
  },
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      "max-video-preview": -1,
      "max-image-preview": "large",
      "max-snippet": -1,
    },
  },
  openGraph: {
    title: "Purple Festival Gujarat 2027 — Inclusion, celebrated.",
    description:
      "21–23 January 2027 · Vadodara, Gujarat. Celebrating accessibility, human dignity, assistive technology, para-sports, and universal design across all 21 Benchmark Disabilities.",
    url: "/",
    type: "website",
    locale: "en_IN",
    siteName: "Purple Festival Gujarat 2027",
    images: [
      {
        url: "/assets/logos/purple-festival.jpg",
        width: 1200,
        height: 630,
        alt: "Purple Festival Gujarat 2027 — Inclusion, celebrated.",
      },
    ],
  },
  twitter: {
    card: "summary_large_image",
    title: "Purple Festival Gujarat 2027 — Inclusion, celebrated.",
    description:
      "21–23 January 2027 · Vadodara, Gujarat. India's landmark festival of inclusion, assistive technology, Paralympic sport, and universal design.",
    images: ["/assets/logos/purple-festival.jpg"],
  },
};

export const viewport: Viewport = {
  themeColor: "#F2E9FE",
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link
          rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..800;1,9..40,400..800&display=swap"
        />
        <link
          rel="preload"
          as="image"
          href="/assets/illustrations/statue-of-unity.png"
        />
        <link
          rel="preload"
          as="image"
          href="/assets/illustrations/somnath-temple.png"
        />
      </head>
      <body className="min-h-screen bg-white text-[#202D59] antialiased selection:bg-[#A459FF]/25 selection:text-[#202D59]">
        <a href="#main-content" className="skip-link">
          Skip to main content
        </a>
        <AccessibilityProvider>
          {children}
          <AccessibilityPanel />
        </AccessibilityProvider>
      </body>
    </html>
  );
}

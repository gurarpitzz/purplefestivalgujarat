import type { Metadata, Viewport } from "next";
import { DM_Sans } from "next/font/google";
import "./globals.css";
import { AccessibilityProvider } from "@/components/AccessibilityProvider";
import { AccessibilityPanel } from "@/components/AccessibilityPanel";

const dmSans = DM_Sans({
  subsets: ["latin"],
  weight: ["400", "500", "600", "700", "800"],
  variable: "--font-dm-sans",
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "Purple Festival Gujarat 2027 | Inclusion, celebrated.",
    template: "%s | Purple Festival Gujarat 2027",
  },
  description:
    "Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara, Gujarat) — India's landmark festival of inclusion, human dignity, assistive technology, Paralympic sport, and universal design across all 21 Benchmark Disabilities (RPwD Act 2016).",
  keywords: [
    "Purple Festival Gujarat 2027",
    "Disability Inclusion",
    "Accessibility India",
    "RPwD Act 2016",
    "Vadodara",
    "Para Sports",
    "Assistive Technology",
    "Inclusive Education",
    "Viksit Bharat 2047",
  ],
  openGraph: {
    title: "Purple Festival Gujarat 2027 — Inclusion, celebrated.",
    description:
      "21–23 January 2027 · Vadodara, Gujarat. Celebrating accessibility, human dignity, assistive technology, para-sports, and universal design.",
    type: "website",
    locale: "en_IN",
    siteName: "Purple Festival Gujarat 2027",
  },
};

export const viewport: Viewport = {
  themeColor: "#DDDDF9",
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" className={dmSans.variable} suppressHydrationWarning>
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

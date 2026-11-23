import React from "react";
import Link from "next/link";
import { SiteHeader } from "@/components/SiteHeader";
import { SiteFooter } from "@/components/SiteFooter";

export default function NotFound() {
  return (
    <>
      <SiteHeader />
      <main
        id="main-content"
        tabIndex={-1}
        className="flex min-h-[70vh] flex-col items-center justify-center bg-[#DDDDF9] px-5 py-20 text-center text-[#202D59]"
      >
        <p className="text-sm font-bold tracking-[0.2em] uppercase text-[#A459FF]">404</p>
        <h1 className="mt-3 text-4xl font-extrabold tracking-tight sm:text-5xl">
          Page not found
        </h1>
        <p className="mt-4 max-w-md text-base text-[#46537C]">
          The page you are looking for may have moved or is not available yet.
        </p>
        <div className="mt-8 flex flex-wrap justify-center gap-4">
          <Link
            href="/"
            className="btn-primary-pfg touch-target inline-flex items-center justify-center bg-[#A459FF] px-6 py-3 text-sm font-bold text-white hover:bg-[#8D3BF2]"
          >
            Back to Home
          </Link>
          <Link
            href="/apply"
            className="touch-target inline-flex items-center justify-center border-2 border-[#202D59] px-6 py-3 text-sm font-bold text-[#202D59] hover:bg-[#202D59] hover:text-white"
          >
            Apply Now
          </Link>
        </div>
      </main>
      <SiteFooter />
    </>
  );
}


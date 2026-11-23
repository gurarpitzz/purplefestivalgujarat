import React from "react";

export function FooterCopyright() {
  return (
    <div
      className="border-t border-[#282530] bg-[#17151C] text-[#B5B5B5]"
      data-surface="dark"
    >
      <div className="mx-auto flex min-h-[64px] max-w-[1380px] flex-col items-start justify-between gap-3 px-5 py-4 text-xs sm:flex-row sm:items-center sm:px-8">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
          <p className="font-medium text-[#E8DFF0]">
            © 2027 Purple Festival Gujarat. All rights reserved.
          </p>
          <span aria-hidden="true" className="hidden sm:inline text-white/25">
            |
          </span>
          <p className="text-[#B6A1C8]">A celebration where everyone belongs.</p>
        </div>

        <p className="text-[11px] text-[#B5B5B5]">
          Organised with Brotherhood · Global Inclusive Education Network (GIEN) · MSU Baroda
        </p>
      </div>
    </div>
  );
}


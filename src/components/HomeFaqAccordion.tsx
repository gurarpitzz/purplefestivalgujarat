"use client";

import React, { useState } from "react";
import { ChevronDown } from "lucide-react";

export interface FaqItemView {
  id: string;
  category: string;
  question: string;
  answer: string;
}

export function HomeFaqAccordion({ items }: { items: FaqItemView[] }) {
  const [openId, setOpenId] = useState<string | null>(items[0]?.id ?? null);

  return (
    <div className="space-y-3">
      {items.map((faq) => {
        const isOpen = openId === faq.id;
        const panelId = `faq-panel-${faq.id}`;
        return (
          <div
            key={faq.id}
            className="overflow-hidden rounded-xl border border-[#202D59]/15 bg-white"
          >
            <button
              type="button"
              onClick={() => setOpenId(isOpen ? null : faq.id)}
              aria-expanded={isOpen}
              aria-controls={panelId}
              className="touch-target flex w-full items-center justify-between gap-4 px-6 py-4 text-left"
            >
              <div>
                <span className="mb-1 block text-xs font-bold tracking-wider uppercase text-[#A459FF]">
                  {faq.category}
                </span>
                <span className="text-base font-bold text-[#202D59] sm:text-lg">
                  {faq.question}
                </span>
              </div>
              <ChevronDown
                className={`h-5 w-5 shrink-0 text-[#202D59] transition-transform ${
                  isOpen ? "rotate-180" : ""
                }`}
                aria-hidden="true"
              />
            </button>
            {isOpen && (
              <div
                id={panelId}
                className="border-t border-[#202D59]/10 px-6 pb-5 pt-4 text-sm leading-relaxed text-[#46537C]"
              >
                {faq.answer}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

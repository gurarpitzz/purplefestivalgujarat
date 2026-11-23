"use client";

import React, { useMemo, useState } from "react";
import { ChevronDown, Search, CheckCircle2, ShieldCheck } from "lucide-react";

export interface DisabilityItem {
  id: string;
  code: string;
  name: string;
  rpwdGroup: string;
  description: string;
  accommodationExamples: string[];
  displayOrder: number;
}

export function DisabilitiesExplorer({
  disabilities,
  initialExpandedCount = 0,
}: {
  disabilities: DisabilityItem[];
  initialExpandedCount?: number;
}) {
  const [selectedGroup, setSelectedGroup] = useState<string>("All");
  const [query, setQuery] = useState("");
  const [expandedIds, setExpandedIds] = useState<Set<string>>(() => {
    const initial = new Set<string>();
    disabilities.slice(0, initialExpandedCount).forEach((d) => initial.add(d.id));
    return initial;
  });

  const groups = useMemo(() => {
    const set = new Set<string>();
    disabilities.forEach((d) => set.add(d.rpwdGroup));
    return ["All", ...Array.from(set)];
  }, [disabilities]);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return disabilities.filter((d) => {
      const matchesGroup = selectedGroup === "All" || d.rpwdGroup === selectedGroup;
      if (!matchesGroup) return false;
      if (!q) return true;
      return (
        d.name.toLowerCase().includes(q) ||
        d.rpwdGroup.toLowerCase().includes(q) ||
        d.description.toLowerCase().includes(q) ||
        d.accommodationExamples.some((ex) => ex.toLowerCase().includes(q))
      );
    });
  }, [disabilities, selectedGroup, query]);

  const toggleItem = (id: string) => {
    setExpandedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  return (
    <div className="space-y-6">
      {/* Search & Group Filter Bar */}
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div className="relative w-full lg:max-w-sm">
          <label htmlFor="disability-search" className="sr-only">
            Search the 21 specified disabilities or accommodations
          </label>
          <Search
            className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#202D59]"
            aria-hidden="true"
          />
          <input
            id="disability-search"
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search disabilities or accommodations..."
            className="touch-target w-full rounded-lg border border-[#202D59]/25 bg-white pl-10 pr-4 text-sm text-[#202D59] placeholder:text-[#46537C] focus:border-[#A459FF]"
          />
        </div>

        <div
          role="group"
          aria-label="Filter disabilities by RPwD category"
          className="flex flex-wrap gap-2"
        >
          {groups.map((grp) => {
            const active = selectedGroup === grp;
            return (
              <button
                key={grp}
                type="button"
                onClick={() => setSelectedGroup(grp)}
                aria-pressed={active}
                className={`touch-target rounded-full border px-3.5 py-2 text-xs font-semibold transition-colors ${
                  active
                    ? "border-[#202D59] bg-[#202D59] text-white"
                    : "border-[#202D59]/20 bg-white text-[#202D59] hover:border-[#A459FF] hover:bg-[#DDDDF9]"
                }`}
              >
                {grp}
              </button>
            );
          })}
        </div>
      </div>

      {/* Live result count */}
      <p className="text-xs font-medium text-[#46537C]" aria-live="polite">
        Showing {filtered.length} of {disabilities.length} specified disabilities under the Rights
        of Persons with Disabilities (RPwD) Act, 2016.
      </p>

      {/* Accordion Grid */}
      {filtered.length === 0 ? (
        <div className="surface-card rounded-2xl border border-[#59436D]/20 bg-white p-8 text-center">
          <p className="text-sm font-medium text-[#211B29]">
            No disabilities match &ldquo;{query}&rdquo;. Try clearing your search filter.
          </p>
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {filtered.map((item) => {
            const isOpen = expandedIds.has(item.id);
            const panelId = `disability-panel-${item.code}`;
            return (
              <article
                key={item.id}
                className="surface-card flex flex-col rounded-2xl border border-[#59436D]/20 bg-white shadow-xs transition-shadow hover:shadow-md"
              >
                <button
                  type="button"
                  onClick={() => toggleItem(item.id)}
                  aria-expanded={isOpen}
                  aria-controls={panelId}
                  className="touch-target flex w-full items-start justify-between gap-3 rounded-2xl p-5 text-left"
                >
                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <span className="inline-flex h-6 min-w-6 items-center justify-center rounded-md bg-[#E8DFF0] px-1.5 text-xs font-bold text-[#432F56]">
                        {String(item.displayOrder).padStart(2, "0")}
                      </span>
                      <span className="text-[11px] font-semibold tracking-wider uppercase text-[#59436D]">
                        {item.rpwdGroup}
                      </span>
                    </div>
                    <h3 className="font-display text-base font-semibold leading-snug text-[#211B29]">
                      {item.name}
                    </h3>
                  </div>
                  <ChevronDown
                    className={`mt-1 h-5 w-5 shrink-0 text-[#59436D] transition-transform ${
                      isOpen ? "rotate-180" : ""
                    }`}
                    aria-hidden="true"
                  />
                </button>

                {isOpen && (
                  <div
                    id={panelId}
                    className="border-t border-[#59436D]/15 px-5 pb-5 pt-4 text-sm text-[#211B29]"
                  >
                    <p className="leading-relaxed text-[#4B4257]">{item.description}</p>
                    {item.accommodationExamples.length > 0 && (
                      <div className="mt-4 rounded-xl bg-[#F6F1F8] p-3.5">
                        <p className="mb-2 flex items-center gap-1.5 text-xs font-bold tracking-wide uppercase text-[#432F56]">
                          <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" />
                          Festival Accommodations
                        </p>
                        <ul className="space-y-1.5 text-xs text-[#211B29]">
                          {item.accommodationExamples.map((ex, idx) => (
                            <li key={idx} className="flex items-start gap-2">
                              <CheckCircle2
                                className="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#19734B]"
                                aria-hidden="true"
                              />
                              <span>{ex}</span>
                            </li>
                          ))}
                        </ul>
                      </div>
                    )}
                  </div>
                )}
              </article>
            );
          })}
        </div>
      )}
    </div>
  );
}

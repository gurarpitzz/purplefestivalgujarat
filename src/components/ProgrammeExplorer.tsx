"use client";

import React, { useMemo, useState } from "react";
import {
  Calendar,
  Clock,
  MapPin,
  Search,
  Download,
  Accessibility,
  Filter,
} from "lucide-react";

export interface ProgrammeItemView {
  id: string;
  dayNumber: number;
  timeSlot: string;
  title: string;
  description: string | null;
  category: string;
  venueName: string | null;
  speakers: Array<{ name: string; role?: string; org?: string }>;
  accessibilityServices: string[];
  displayOrder: number;
}

const DAY_META: Record<number, { label: string; dateDisplay: string; isoDate: string }> = {
  1: { label: "Day 1", dateDisplay: "Thursday, 21 January 2027", isoDate: "20270121" },
  2: { label: "Day 2", dateDisplay: "Friday, 22 January 2027", isoDate: "20270122" },
  3: { label: "Day 3", dateDisplay: "Saturday, 23 January 2027", isoDate: "20270123" },
};

function parseTimeRangeToUtc(isoDate: string, timeSlot: string): { dtStart: string; dtEnd: string } {
  // Matches e.g. "09:30 – 11:00 IST" or "14:00 - 16:30"
  const match = timeSlot.match(/(\d{1,2}):(\d{2})\s*[–-]\s*(\d{1,2}):(\d{2})/);
  const year = Number(isoDate.slice(0, 4));
  const month = Number(isoDate.slice(4, 6)) - 1;
  const day = Number(isoDate.slice(6, 8));

  const sh = match ? Number(match[1]) : 9;
  const sm = match ? Number(match[2]) : 0;
  const eh = match ? Number(match[3]) : sh + 1;
  const em = match ? Number(match[4]) : sm;

  // IST is UTC+5:30 -> subtract 330 minutes
  const startUtc = new Date(Date.UTC(year, month, day, sh, sm) - 330 * 60 * 1000);
  const endUtc = new Date(Date.UTC(year, month, day, eh, em) - 330 * 60 * 1000);

  const fmt = (d: Date) =>
    d
      .toISOString()
      .replace(/[-:]/g, "")
      .replace(/\.\d{3}Z$/, "Z");

  return { dtStart: fmt(startUtc), dtEnd: fmt(endUtc) };
}

function escapeIcsText(val: string): string {
  return val
    .replace(/\\/g, "\\\\")
    .replace(/\n/g, "\\n")
    .replace(/,/g, "\\,")
    .replace(/;/g, "\\;");
}

function buildIcsFile(items: ProgrammeItemView[]): string {
  const lines: string[] = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//Purple Festival Gujarat 2027//Programme//EN",
    "CALSCALE:GREGORIAN",
    "METHOD:PUBLISH",
    "X-WR-CALNAME:Purple Festival Gujarat 2027",
  ];

  const dtStamp = new Date()
    .toISOString()
    .replace(/[-:]/g, "")
    .replace(/\.\d{3}Z$/, "Z");

  for (const item of items) {
    const meta = DAY_META[item.dayNumber] ?? DAY_META[1];
    const { dtStart, dtEnd } = parseTimeRangeToUtc(meta.isoDate, item.timeSlot);
    const descParts = [
      item.description || "",
      item.accessibilityServices.length > 0
        ? `Accessibility Services: ${item.accessibilityServices.join(", ")}`
        : "",
    ].filter(Boolean);

    lines.push(
      "BEGIN:VEVENT",
      `UID:pfg2027-${item.id}@purplefestivalgujarat.org`,
      `DTSTAMP:${dtStamp}`,
      `DTSTART:${dtStart}`,
      `DTEND:${dtEnd}`,
      `SUMMARY:${escapeIcsText(`${item.title} — Purple Festival Gujarat 2027`)}`,
      `DESCRIPTION:${escapeIcsText(descParts.join(" | "))}`,
      `LOCATION:${escapeIcsText(item.venueName || "Vadodara, Gujarat")}`,
      `CATEGORIES:${escapeIcsText(item.category)}`,
      "END:VEVENT"
    );
  }

  lines.push("END:VCALENDAR");
  return lines.join("\r\n");
}

function triggerIcsDownload(filename: string, content: string) {
  const blob = new Blob([content], { type: "text/calendar;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export function ProgrammeExplorer({ items }: { items: ProgrammeItemView[] }) {
  const [activeDay, setActiveDay] = useState<number>(0); // 0 = All 3 Days
  const [selectedCategory, setSelectedCategory] = useState<string>("All");
  const [query, setQuery] = useState("");

  const categories = useMemo(() => {
    const set = new Set<string>();
    items.forEach((i) => set.add(i.category));
    return ["All", ...Array.from(set)];
  }, [items]);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return items.filter((item) => {
      if (activeDay !== 0 && item.dayNumber !== activeDay) return false;
      if (selectedCategory !== "All" && item.category !== selectedCategory) return false;
      if (!q) return true;
      return (
        item.title.toLowerCase().includes(q) ||
        (item.description ?? "").toLowerCase().includes(q) ||
        (item.venueName ?? "").toLowerCase().includes(q) ||
        item.category.toLowerCase().includes(q) ||
        item.accessibilityServices.some((s) => s.toLowerCase().includes(q))
      );
    });
  }, [items, activeDay, selectedCategory, query]);

  return (
    <div className="space-y-8">
      {/* Day Tabs + Download Full Schedule */}
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div
          role="tablist"
          aria-label="Festival Days"
          className="flex flex-wrap gap-2"
        >
          <button
            type="button"
            role="tab"
            aria-selected={activeDay === 0}
            onClick={() => setActiveDay(0)}
            className={`touch-target rounded-xl border px-4 py-2.5 text-sm font-semibold transition-colors ${
              activeDay === 0
                ? "border-[#59436D] bg-[#59436D] text-white"
                : "border-[#59436D]/25 bg-white text-[#211B29] hover:bg-[#E8DFF0]"
            }`}
          >
            All 3 Days
          </button>
          {[1, 2, 3].map((day) => {
            const meta = DAY_META[day];
            const selected = activeDay === day;
            return (
              <button
                key={day}
                type="button"
                role="tab"
                aria-selected={selected}
                onClick={() => setActiveDay(day)}
                className={`touch-target rounded-xl border px-4 py-2.5 text-left text-sm transition-colors ${
                  selected
                    ? "border-[#59436D] bg-[#59436D] text-white"
                    : "border-[#59436D]/25 bg-white text-[#211B29] hover:bg-[#E8DFF0]"
                }`}
              >
                <span className="font-bold">{meta.label}</span>
                <span className={`ml-2 text-xs ${selected ? "text-[#E8DFF0]" : "text-[#4B4257]"}`}>
                  {day === 1 ? "21 Jan" : day === 2 ? "22 Jan" : "23 Jan"}
                </span>
              </button>
            );
          })}
        </div>

        <button
          type="button"
          onClick={() =>
            triggerIcsDownload(
              activeDay === 0
                ? "purple-festival-gujarat-2027-full-programme.ics"
                : `purple-festival-gujarat-2027-day-${activeDay}.ics`,
              buildIcsFile(filtered.length > 0 ? filtered : items)
            )
          }
          className="touch-target inline-flex items-center justify-center gap-2 rounded-xl border border-[#59436D] bg-[#17151C] px-4 py-2.5 text-sm font-semibold text-[#F9F7FC] transition-colors hover:border-[#D5B77B] hover:text-[#D5B77B]"
        >
          <Download className="h-4 w-4 text-[#D5B77B]" aria-hidden="true" />
          <span>
            Save {activeDay === 0 ? "Filtered Schedule" : `Day ${activeDay}`} (.ics)
          </span>
        </button>
      </div>

      {/* Search & Category Filter */}
      <div className="surface-card flex flex-col gap-4 rounded-2xl border border-[#59436D]/20 bg-white p-4 sm:flex-row sm:items-center">
        <div className="relative flex-1">
          <label htmlFor="programme-search" className="sr-only">
            Search sessions, venues, or accessibility services
          </label>
          <Search
            className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#59436D]"
            aria-hidden="true"
          />
          <input
            id="programme-search"
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search sessions, venues, ISL, Braille, para-sports..."
            className="touch-target w-full rounded-xl border border-[#59436D]/25 bg-[#F6F1F8] pl-10 pr-4 text-sm text-[#211B29] placeholder:text-[#4B4257]"
          />
        </div>

        <div className="flex items-center gap-2">
          <Filter className="h-4 w-4 shrink-0 text-[#59436D]" aria-hidden="true" />
          <label htmlFor="programme-category" className="sr-only">
            Filter by session category
          </label>
          <select
            id="programme-category"
            value={selectedCategory}
            onChange={(e) => setSelectedCategory(e.target.value)}
            className="touch-target rounded-xl border border-[#59436D]/25 bg-[#F6F1F8] px-3.5 text-sm font-medium text-[#211B29]"
          >
            {categories.map((cat) => (
              <option key={cat} value={cat}>
                {cat === "All" ? "All Categories" : cat}
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Results Count */}
      <p className="text-xs font-semibold text-[#4B4257]" aria-live="polite">
        Showing {filtered.length} session{filtered.length === 1 ? "" : "s"}
      </p>

      {/* Session Cards */}
      {filtered.length === 0 ? (
        <div className="surface-card rounded-2xl border border-[#59436D]/20 bg-white p-10 text-center">
          <p className="text-base font-semibold text-[#211B29]">No matching sessions found.</p>
          <p className="mt-1 text-sm text-[#4B4257]">
            Try selecting a different day or clearing your search keywords.
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          {filtered.map((item) => {
            const meta = DAY_META[item.dayNumber] ?? DAY_META[1];
            return (
              <article
                key={item.id}
                className="surface-card rounded-2xl border border-[#59436D]/20 bg-white p-6 shadow-xs transition-shadow hover:shadow-md"
              >
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  <div className="space-y-2.5">
                    <div className="flex flex-wrap items-center gap-2 text-xs font-semibold">
                      <span className="inline-flex items-center gap-1.5 rounded-full bg-[#17151C] px-3 py-1 text-[#D5B77B]">
                        <Calendar className="h-3.5 w-3.5" aria-hidden="true" />
                        {meta.label} · {meta.dateDisplay}
                      </span>
                      <span className="inline-flex items-center gap-1.5 rounded-full bg-[#E8DFF0] px-3 py-1 text-[#432F56]">
                        <Clock className="h-3.5 w-3.5" aria-hidden="true" />
                        {item.timeSlot}
                      </span>
                      <span className="rounded-full border border-[#59436D]/25 bg-[#F6F1F8] px-3 py-1 text-[#59436D]">
                        {item.category}
                      </span>
                    </div>

                    <h2 className="font-display text-xl font-semibold text-[#211B29]">
                      {item.title}
                    </h2>

                    {item.description && (
                      <p className="max-w-3xl text-sm leading-relaxed text-[#4B4257]">
                        {item.description}
                      </p>
                    )}

                    {item.venueName && (
                      <p className="flex items-center gap-1.5 text-sm font-medium text-[#211B29]">
                        <MapPin className="h-4 w-4 shrink-0 text-[#59436D]" aria-hidden="true" />
                        <span>{item.venueName}</span>
                      </p>
                    )}

                    {item.accessibilityServices.length > 0 && (
                      <div className="flex flex-wrap items-center gap-2 pt-1">
                        <span className="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-[#19734B]">
                          <Accessibility className="h-3.5 w-3.5" aria-hidden="true" />
                          Access:
                        </span>
                        {item.accessibilityServices.map((svc, idx) => (
                          <span
                            key={idx}
                            className="rounded-md border border-[#19734B]/25 bg-[#19734B]/10 px-2.5 py-0.5 text-xs font-semibold text-[#135A3A]"
                          >
                            {svc}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>

                  <div className="shrink-0">
                    <button
                      type="button"
                      onClick={() =>
                        triggerIcsDownload(
                          `pfg2027-day${item.dayNumber}-${item.title
                            .toLowerCase()
                            .replace(/[^a-z0-9]+/g, "-")
                            .slice(0, 40)}.ics`,
                          buildIcsFile([item])
                        )
                      }
                      aria-label={`Save ${item.title} to calendar`}
                      className="touch-target inline-flex items-center gap-2 rounded-xl border border-[#59436D]/30 bg-[#F6F1F8] px-4 py-2 text-xs font-bold text-[#432F56] transition-colors hover:border-[#59436D] hover:bg-[#59436D] hover:text-white"
                    >
                      <Download className="h-3.5 w-3.5" aria-hidden="true" />
                      Add to Calendar (.ics)
                    </button>
                  </div>
                </div>
              </article>
            );
          })}
        </div>
      )}
    </div>
  );
}

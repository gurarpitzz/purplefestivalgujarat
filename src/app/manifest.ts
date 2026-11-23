import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "Purple Festival Gujarat 2027 — Inclusion, celebrated.",
    short_name: "PurpleFest 2027",
    description:
      "India's landmark festival of inclusion, human dignity, assistive technology, Paralympic sport, and universal design across all 21 Benchmark Disabilities (21–23 Jan 2027, Vadodara).",
    start_url: "/",
    display: "standalone",
    background_color: "#F2E9FE",
    theme_color: "#A459FF",
    icons: [
      {
        src: "/assets/logos/purple-festival.jpg",
        sizes: "512x512",
        type: "image/jpeg",
      },
    ],
  };
}

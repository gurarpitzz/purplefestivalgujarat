import type { MetadataRoute } from "next";

export default function robots(): MetadataRoute.Robots {
  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || "https://purplefestivalgujarat.com";
  return {
    rules: [
      {
        userAgent: "*",
        allow: ["/", "/programme", "/accessibility", "/apply", "/legal/"],
        disallow: ["/admin", "/rgst", "/dashboard", "/api/"],
      },
    ],
    sitemap: `${baseUrl}/sitemap.xml`,
    host: baseUrl,
  };
}

import { defineConfig } from "drizzle-kit";

export default defineConfig({
  schema: "./src/db/schema.ts",
  out: "./drizzle",
  dialect: "postgresql",
  dbCredentials: {
    url: process.env.DATABASE_URL || "postgresql://pfg_admin:pfg2027_change_me@localhost:5432/purple_festival_gujarat_2027",
  },
});


import { migrate as migratePg } from "drizzle-orm/node-postgres/migrator";
import { migrate as migratePglite } from "drizzle-orm/pglite/migrator";
import path from "path";
import { getDb } from "./index";
import type { NodePgDatabase } from "drizzle-orm/node-postgres";
import type { PgliteDatabase } from "drizzle-orm/pglite";
import type * as schema from "./schema";

export async function runMigrations() {
  const db = getDb();
  const migrationsFolder = path.resolve(process.cwd(), "drizzle");
  const databaseUrl = process.env.DATABASE_URL?.trim();

  if (databaseUrl && (databaseUrl.startsWith("postgres://") || databaseUrl.startsWith("postgresql://"))) {
    await migratePg(db as NodePgDatabase<typeof schema>, { migrationsFolder });
  } else {
    await migratePglite(db as PgliteDatabase<typeof schema>, { migrationsFolder });
  }
}

if (require.main === module) {
  runMigrations()
    .then(() => {
      console.log("Database migrations applied successfully.");
      process.exit(0);
    })
    .catch((err) => {
      console.error("Migration failed:", err);
      process.exit(1);
    });
}


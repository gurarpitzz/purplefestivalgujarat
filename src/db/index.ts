import { drizzle as drizzlePg, type NodePgDatabase } from "drizzle-orm/node-postgres";
import { drizzle as drizzlePglite, type PgliteDatabase } from "drizzle-orm/pglite";
import { Pool } from "pg";
import { PGlite } from "@electric-sql/pglite";
import path from "path";
import fs from "fs";
import * as schema from "./schema";

export type AppDatabase = NodePgDatabase<typeof schema> | PgliteDatabase<typeof schema>;

const globalForDb = globalThis as unknown as {
  __pfgDb?: AppDatabase;
  __pfgPool?: Pool;
  __pfgPglite?: PGlite;
};

function createDb(): AppDatabase {
  const databaseUrl = process.env.DATABASE_URL?.trim();

  if (databaseUrl && (databaseUrl.startsWith("postgres://") || databaseUrl.startsWith("postgresql://"))) {
    const pool =
      globalForDb.__pfgPool ??
      new Pool({
        connectionString: databaseUrl,
        max: 15,
      });
    globalForDb.__pfgPool = pool;
    return drizzlePg(pool, { schema });
  }

  // Real embedded PostgreSQL (PGlite) for zero-daemon local development & CI tests
  const dataDir = process.env.PGLITE_DATA_DIR || "./data/pgdata";
  const resolvedDir =
    dataDir === "memory://"
      ? undefined
      : path.isAbsolute(dataDir)
        ? dataDir
        : path.resolve(process.cwd(), dataDir);

  if (resolvedDir) {
    fs.mkdirSync(resolvedDir, { recursive: true });
  }

  const client =
    globalForDb.__pfgPglite ?? (resolvedDir ? new PGlite(resolvedDir) : new PGlite());
  globalForDb.__pfgPglite = client;
  return drizzlePglite(client, { schema });
}

export function getDb(): AppDatabase {
  if (!globalForDb.__pfgDb) {
    globalForDb.__pfgDb = createDb();
  }
  return globalForDb.__pfgDb;
}

export const db = new Proxy({} as AppDatabase, {
  get(_target, prop, receiver) {
    const instance = getDb();
    const value = Reflect.get(instance, prop, receiver);
    return typeof value === "function" ? value.bind(instance) : value;
  },
});

export { schema };


import bcrypt from "bcryptjs";
import { eq } from "drizzle-orm";
import { db, schema } from "./index";

async function bootstrapSuperadmin() {
  const email = process.env.SUPERADMIN_BOOTSTRAP_EMAIL?.trim().toLowerCase();
  const password = process.env.SUPERADMIN_BOOTSTRAP_PASSWORD;
  const fullName = process.env.SUPERADMIN_BOOTSTRAP_NAME?.trim() || "Festival Superadmin";

  if (!email || !password) {
    console.error(
      "ERROR: Set SUPERADMIN_BOOTSTRAP_EMAIL and SUPERADMIN_BOOTSTRAP_PASSWORD in your environment before running db:bootstrap-superadmin."
    );
    process.exit(1);
  }

  if (password.length < 12) {
    console.error("ERROR: SUPERADMIN_BOOTSTRAP_PASSWORD must be at least 12 characters.");
    process.exit(1);
  }

  const [superadminRole] = await db
    .select()
    .from(schema.roles)
    .where(eq(schema.roles.code, "SUPERADMIN"))
    .limit(1);

  if (!superadminRole) {
    console.error("ERROR: Roles not seeded yet. Run `npm run db:seed` first.");
    process.exit(1);
  }

  const existingSuperadmins = await db
    .select()
    .from(schema.userRoles)
    .where(eq(schema.userRoles.roleId, superadminRole.id))
    .limit(1);

  if (existingSuperadmins.length > 0) {
    console.log("A SUPERADMIN account already exists. Bootstrap skipped for safety.");
    process.exit(0);
  }

  const passwordHash = await bcrypt.hash(password, 12);

  const [existingUser] = await db
    .select()
    .from(schema.users)
    .where(eq(schema.users.email, email))
    .limit(1);

  let userId: string;
  if (existingUser) {
    await db
      .update(schema.users)
      .set({ passwordHash, fullName, isActive: true, emailVerifiedAt: new Date() })
      .where(eq(schema.users.id, existingUser.id));
    userId = existingUser.id;
  } else {
    const [created] = await db
      .insert(schema.users)
      .values({
        email,
        passwordHash,
        fullName,
        country: "India",
        isActive: true,
        emailVerifiedAt: new Date(),
      })
      .returning();
    userId = created.id;
  }

  await db
    .insert(schema.userRoles)
    .values({
      userId,
      roleId: superadminRole.id,
    })
    .onConflictDoNothing();

  await db.insert(schema.auditLogs).values({
    actorUserId: userId,
    action: "SUPERADMIN_BOOTSTRAPPED",
    resourceType: "users",
    resourceId: userId,
    metadata: { email },
  });

  console.log(`Superadmin initialized successfully for ${email}.`);
  process.exit(0);
}

bootstrapSuperadmin().catch((err) => {
  console.error("Superadmin bootstrap failed:", err);
  process.exit(1);
});


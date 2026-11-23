import { redirect } from "next/navigation";
import { asc, desc, eq } from "drizzle-orm";
import { db, schema } from "@/db";
import { getCurrentUser, hasPermission, hasRole } from "@/lib/auth";
import {
  formatIstTimestamp,
  getIstDateString,
  PERMISSIONS,
} from "@/lib/constants";
import RgstScannerPortal from "@/components/RgstScannerPortal";

export const dynamic = "force-dynamic";

export const metadata = {
  title: "RGST Reception Desk — Purple Festival Gujarat 2027",
  description:
    "Authorized QR verification and venue check-in reception desk portal for Purple Festival Gujarat 2027.",
};

export default async function RgstPage() {
  const user = await getCurrentUser();
  if (!user) {
    redirect("/login");
  }

  if (!hasPermission(user, PERMISSIONS.QR_SCAN)) {
    redirect("/dashboard");
  }

  const todayIst = getIstDateString();

  const stations = await db
    .select({
      id: schema.checkinStations.id,
      name: schema.checkinStations.name,
      gateCode: schema.checkinStations.gateCode,
    })
    .from(schema.checkinStations)
    .where(eq(schema.checkinStations.isActive, true))
    .orderBy(asc(schema.checkinStations.name));

  const ownRows = await db
    .select({
      id: schema.checkins.id,
      venueLabel: schema.checkins.venueLabel,
      method: schema.checkins.method,
      checkinDateIst: schema.checkins.checkinDateIst,
      checkedInAt: schema.checkins.checkedInAt,
      participantName: schema.registrations.fullName,
      registrationCode: schema.registrations.registrationCode,
      credentialCode: schema.qrCredentials.credentialCode,
      categoryCode: schema.registrations.categoryCode,
    })
    .from(schema.checkins)
    .innerJoin(
      schema.registrations,
      eq(schema.checkins.registrationId, schema.registrations.id)
    )
    .innerJoin(
      schema.qrCredentials,
      eq(schema.checkins.credentialId, schema.qrCredentials.id)
    )
    .where(eq(schema.checkins.operatorUserId, user.id))
    .orderBy(desc(schema.checkins.checkedInAt))
    .limit(50);

  const initialTodayCount = ownRows.filter(
    (row) => row.checkinDateIst === todayIst
  ).length;

  const initialRecentCheckins = ownRows.map((r) => ({
    id: r.id,
    participantName: r.participantName,
    registrationCode: r.registrationCode,
    credentialCode: r.credentialCode,
    categoryCode: r.categoryCode,
    venueLabel: r.venueLabel,
    method: r.method,
    checkinDateIst: r.checkinDateIst,
    checkedInAt: r.checkedInAt.toISOString(),
    checkedInAtIst: formatIstTimestamp(r.checkedInAt),
  }));

  const isAdmin = hasRole(user, ["ADMIN", "SUPERADMIN"]);

  return (
    <RgstScannerPortal
      operator={{
        id: user.id,
        fullName: user.fullName,
        email: user.email,
        roleLabel: user.roles.includes("SUPERADMIN")
          ? "SUPERADMIN"
          : user.roles.includes("ADMIN")
            ? "ADMIN"
            : "RGST",
        isAdmin,
      }}
      initialStations={stations}
      initialRecentCheckins={initialRecentCheckins}
      initialTodayCount={initialTodayCount}
      todayIst={todayIst}
    />
  );
}

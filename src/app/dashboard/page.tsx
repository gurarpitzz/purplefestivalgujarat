import React from "react";
import { redirect } from "next/navigation";
import { and, asc, desc, eq, inArray, isNull } from "drizzle-orm";
import { db, schema } from "@/db";
import { getCurrentUser } from "@/lib/auth";
import { generateQrDataUrl } from "@/lib/qr";
import ParticipantDashboard, {
  type DashboardRegistration,
} from "@/components/ParticipantDashboard";

export const dynamic = "force-dynamic";

export const metadata = {
  title: "Participant Dashboard | Purple Festival Gujarat 2027",
  description:
    "Manage your registrations, digital QR entry passes, volunteer/SLI duty assignments, accessibility accommodations, and account security.",
};

export default async function DashboardPage() {
  const user = await getCurrentUser();
  if (!user) {
    redirect("/login?next=/dashboard");
  }

  const [userRegs, categories, accommodationTypes] = await Promise.all([
    db
      .select()
      .from(schema.registrations)
      .where(
        and(
          eq(schema.registrations.userId, user.id),
          isNull(schema.registrations.deletedAt)
        )
      )
      .orderBy(desc(schema.registrations.createdAt)),
    db.select().from(schema.registrationCategories),
    db
      .select()
      .from(schema.accommodationTypes)
      .where(eq(schema.accommodationTypes.isActive, true))
      .orderBy(asc(schema.accommodationTypes.displayOrder)),
  ]);

  const regIds = userRegs.map((r) => r.id);

  const [creds, volApps, sliApps, accommReqs, userCheckins] =
    regIds.length > 0
      ? await Promise.all([
          db
            .select()
            .from(schema.qrCredentials)
            .where(inArray(schema.qrCredentials.registrationId, regIds))
            .orderBy(desc(schema.qrCredentials.issuedAt)),
          db
            .select()
            .from(schema.volunteerApplications)
            .where(inArray(schema.volunteerApplications.registrationId, regIds)),
          db
            .select()
            .from(schema.sliApplications)
            .where(inArray(schema.sliApplications.registrationId, regIds)),
          db
            .select()
            .from(schema.accommodationRequests)
            .where(inArray(schema.accommodationRequests.registrationId, regIds))
            .orderBy(desc(schema.accommodationRequests.createdAt)),
          db
            .select()
            .from(schema.checkins)
            .where(inArray(schema.checkins.registrationId, regIds))
            .orderBy(desc(schema.checkins.checkedInAt)),
        ])
      : [[], [], [], [], []];

  const categoryNameMap = new Map(categories.map((c) => [c.code, c.name]));

  const registrations: DashboardRegistration[] = await Promise.all(
    userRegs.map(async (reg) => {
      const activeCred = creds.find(
        (c) => c.registrationId === reg.id && c.status === "ACTIVE"
      );
      const qrDataUrl = activeCred
        ? await generateQrDataUrl(activeCred.credentialCode)
        : null;

      const vol = volApps.find((v) => v.registrationId === reg.id);
      const sli = sliApps.find((s) => s.registrationId === reg.id);

      return {
        id: reg.id,
        registrationCode: reg.registrationCode,
        categoryCode: reg.categoryCode,
        categoryName: categoryNameMap.get(reg.categoryCode) || reg.categoryCode,
        status: reg.status,
        paymentStatus: reg.paymentStatus,
        fullName: reg.fullName,
        email: reg.email,
        attendingDays: reg.attendingDays || [],
        reviewNotes: reg.reviewNotes,
        createdAt: reg.createdAt.toISOString(),
        activeCredential: activeCred
          ? {
              id: activeCred.id,
              credentialCode: activeCred.credentialCode,
              status: activeCred.status,
              issuedAt: activeCred.issuedAt.toISOString(),
              qrDataUrl,
            }
          : null,
        volunteerAssignment: vol
          ? {
              assignedDepartment: vol.assignedDepartment,
              assignedResponsibility: vol.assignedResponsibility,
              assignedSupervisor: vol.assignedSupervisor,
              assignedVenue: vol.assignedVenue,
              assignedShift: vol.assignedShift,
              reportingTime: vol.reportingTime,
              assignmentNotes: vol.assignmentNotes,
            }
          : null,
        sliAssignment: sli
          ? {
              assignedVenue: sli.assignedVenue,
              dutyTimes: sli.dutyTimes,
              assignedSessions: sli.assignedSessions || [],
              assignmentNotes: sli.assignmentNotes,
            }
          : null,
        checkins: userCheckins
          .filter((c) => c.registrationId === reg.id)
          .map((c) => ({
            id: c.id,
            venueLabel: c.venueLabel,
            checkinDateIst: c.checkinDateIst,
            checkedInAt: c.checkedInAt.toISOString(),
          })),
      };
    })
  );

  const regCodeMap = new Map(
    userRegs.map((r) => [
      r.id,
      { code: r.registrationCode, categoryCode: r.categoryCode },
    ])
  );

  const initialAccommodations = accommReqs.map((a) => ({
    id: a.id,
    registrationId: a.registrationId,
    registrationCode: regCodeMap.get(a.registrationId)?.code || "",
    categoryCode: regCodeMap.get(a.registrationId)?.categoryCode || "",
    selectedTypes: a.selectedTypes || [],
    freeTextDetails: a.freeTextDetails,
    status: a.status,
    createdAt: a.createdAt.toISOString(),
  }));

  return (
    <ParticipantDashboard
      user={{
        id: user.id,
        fullName: user.fullName,
        email: user.email,
        phone: user.phone,
        country: user.country,
        roles: user.roles,
        mfaEnabled: user.mfaEnabled,
      }}
      registrations={registrations}
      accommodationTypes={accommodationTypes.map((t) => ({
        code: t.code,
        label: t.label,
      }))}
      initialAccommodations={initialAccommodations}
    />
  );
}

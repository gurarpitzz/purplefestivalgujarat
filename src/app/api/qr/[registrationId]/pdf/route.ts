import { NextRequest, NextResponse } from "next/server";
import { and, desc, eq } from "drizzle-orm";
import { db, schema } from "@/db";
import { getCurrentUser, hasRole } from "@/lib/auth";
import { generateAccessiblePassPdf } from "@/lib/pass-pdf";

export async function GET(
  _req: NextRequest,
  context: { params: Promise<{ registrationId: string }> }
) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return NextResponse.json({ error: "Authentication required." }, { status: 401 });
    }

    const { registrationId } = await context.params;
    if (!registrationId) {
      return NextResponse.json({ error: "Registration ID is required." }, { status: 400 });
    }

    const [reg] = await db
      .select({
        id: schema.registrations.id,
        registrationCode: schema.registrations.registrationCode,
        userId: schema.registrations.userId,
        categoryId: schema.registrations.categoryId,
        categoryCode: schema.registrations.categoryCode,
        status: schema.registrations.status,
        fullName: schema.registrations.fullName,
      })
      .from(schema.registrations)
      .where(eq(schema.registrations.id, registrationId))
      .limit(1);

    if (!reg) {
      return NextResponse.json({ error: "Registration not found." }, { status: 404 });
    }

    const isOwner = reg.userId === user.id;
    const isAdmin = hasRole(user, ["ADMIN", "SUPERADMIN"]);
    if (!isOwner && !isAdmin) {
      return NextResponse.json(
        { error: "You do not have permission to download this pass." },
        { status: 403 }
      );
    }

    const [credential] = await db
      .select()
      .from(schema.qrCredentials)
      .where(
        and(
          eq(schema.qrCredentials.registrationId, reg.id),
          eq(schema.qrCredentials.status, "ACTIVE")
        )
      )
      .orderBy(desc(schema.qrCredentials.issuedAt))
      .limit(1);

    if (!credential) {
      return NextResponse.json(
        { error: "No active QR credential available for PDF download." },
        { status: 404 }
      );
    }

    const [category] = await db
      .select({ name: schema.registrationCategories.name })
      .from(schema.registrationCategories)
      .where(eq(schema.registrationCategories.id, reg.categoryId))
      .limit(1);

    let roleAssignment: string | null = null;
    if (reg.categoryCode === "VOLUNTEER") {
      const [vol] = await db
        .select({
          assignedDepartment: schema.volunteerApplications.assignedDepartment,
          assignedVenue: schema.volunteerApplications.assignedVenue,
          assignedShift: schema.volunteerApplications.assignedShift,
        })
        .from(schema.volunteerApplications)
        .where(eq(schema.volunteerApplications.registrationId, reg.id))
        .limit(1);
      if (vol?.assignedDepartment || vol?.assignedVenue) {
        roleAssignment = [
          vol.assignedDepartment,
          vol.assignedVenue,
          vol.assignedShift,
        ]
          .filter(Boolean)
          .join(" • ");
      }
    } else if (reg.categoryCode === "SLI") {
      const [sli] = await db
        .select({
          assignedVenue: schema.sliApplications.assignedVenue,
          dutyTimes: schema.sliApplications.dutyTimes,
        })
        .from(schema.sliApplications)
        .where(eq(schema.sliApplications.registrationId, reg.id))
        .limit(1);
      if (sli?.assignedVenue || sli?.dutyTimes) {
        roleAssignment = [sli.assignedVenue, sli.dutyTimes].filter(Boolean).join(" • ");
      }
    }

    const pdfBytes = await generateAccessiblePassPdf({
      participantName: reg.fullName,
      registrationCode: reg.registrationCode,
      credentialCode: credential.credentialCode,
      categoryName: category?.name ?? reg.categoryCode,
      status: reg.status,
      qrPayload: credential.credentialCode,
      roleAssignment,
    });

    const safeFileCode = credential.credentialCode.replace(/[^A-Za-z0-9_-]/g, "");

    return new NextResponse(Buffer.from(pdfBytes), {
      status: 200,
      headers: {
        "Content-Type": "application/pdf",
        "Content-Disposition": `attachment; filename="PFG-2027-Pass-${safeFileCode}.pdf"`,
        "Cache-Control": "no-store",
      },
    });
  } catch (err) {
    console.error("[GET /api/qr/[registrationId]/pdf] Error:", err);
    return NextResponse.json(
      { error: "Failed to generate pass PDF." },
      { status: 500 }
    );
  }
}

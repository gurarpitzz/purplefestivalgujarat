import { NextRequest, NextResponse } from "next/server";
import { destroyCurrentSession, getCurrentUser, recordAuditLog } from "@/lib/auth";

export async function POST(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    await destroyCurrentSession();

    if (user) {
      const ipAddress =
        req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ||
        req.headers.get("x-real-ip") ||
        null;
      const userAgent = req.headers.get("user-agent") || null;

      await recordAuditLog({
        actorUserId: user.id,
        action: "LOGOUT",
        resourceType: "session",
        resourceId: user.sessionId,
        ipAddress,
        userAgent,
      });
    }

    return NextResponse.json({ success: true });
  } catch (err) {
    console.error("POST /api/auth/logout error:", err);
    return NextResponse.json(
      { error: "Unable to sign out cleanly." },
      { status: 500 }
    );
  }
}

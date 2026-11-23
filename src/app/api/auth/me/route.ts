import { NextResponse } from "next/server";
import { getCurrentUser } from "@/lib/auth";

export async function GET() {
  try {
    const user = await getCurrentUser();
    return NextResponse.json({ user: user ?? null });
  } catch (err) {
    console.error("GET /api/auth/me error:", err);
    return NextResponse.json({ user: null });
  }
}

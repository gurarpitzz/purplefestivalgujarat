import { redirect } from "next/navigation";
import { getCurrentUser, hasRole } from "@/lib/auth";
import AdminPortal from "@/components/AdminPortal";

export const metadata = {
  title: "Admin Portal — Purple Festival Gujarat 2027",
  description:
    "Administrative operations, volunteer & SLI review, accessibility coordination, CMS, and attendance analytics for Purple Festival Gujarat 2027.",
};

export default async function AdminPage() {
  const user = await getCurrentUser();

  if (!user) {
    redirect("/login");
  }

  if (!hasRole(user, ["SUPERADMIN", "ADMIN"])) {
    if (user.roles.includes("RGST")) {
      redirect("/rgst");
    }
    redirect("/dashboard");
  }

  return (
    <AdminPortal
      currentUser={{
        id: user.id,
        fullName: user.fullName,
        email: user.email,
        roles: user.roles,
        permissions: user.permissions,
      }}
    />
  );
}

"use client";

import React, { Suspense, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import {
  AlertCircle,
  ArrowLeft,
  CheckCircle2,
  Eye,
  EyeOff,
  KeyRound,
  Lock,
  Mail,
  ShieldCheck,
  UserPlus,
} from "lucide-react";

type AuthMode = "login" | "signup" | "forgot" | "reset";

function LoginPortalContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const resetTokenParam = searchParams.get("resetToken");
  const nextParam = searchParams.get("next");
  const modeParam = searchParams.get("mode");

  const [mode, setMode] = useState<AuthMode>(() => {
    if (resetTokenParam) return "reset";
    if (modeParam === "signup") return "signup";
    return "login";
  });

  // Login state
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [mfaRequired, setMfaRequired] = useState(false);
  const [mfaCode, setMfaCode] = useState("");

  // Signup state
  const [fullName, setFullName] = useState("");
  const [phone, setPhone] = useState("");
  const [country, setCountry] = useState("India");

  // Reset state
  const [resetToken, setResetToken] = useState(resetTokenParam || "");
  const [newPassword, setNewPassword] = useState("");

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  useEffect(() => {
    if (resetTokenParam) {
      setResetToken(resetTokenParam);
      setMode("reset");
    }
  }, [resetTokenParam]);

  function switchMode(nextMode: AuthMode) {
    setMode(nextMode);
    setError(null);
    setSuccessMsg(null);
    setMfaRequired(false);
    setMfaCode("");
  }

  async function handleLogin(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSuccessMsg(null);
    setLoading(true);
    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email: email.trim(),
          password,
          ...(mfaRequired && mfaCode ? { mfaCode: mfaCode.trim() } : {}),
        }),
      });
      const data = await res.json().catch(() => ({}));

      if (data.mfaRequired && !res.ok) {
        setMfaRequired(true);
        setError(data.error || "Invalid authenticator code.");
        return;
      }

      if (data.mfaRequired && res.ok) {
        setMfaRequired(true);
        setSuccessMsg(
          data.message || "Enter the 6-digit code from your authenticator app."
        );
        return;
      }

      if (!res.ok) {
        setError(data.error || "Sign-in failed. Please check your credentials.");
        return;
      }

      const target =
        nextParam && nextParam.startsWith("/") && !nextParam.startsWith("//")
          ? nextParam
          : data.redirectTo || "/dashboard";
      router.push(target);
      router.refresh();
    } catch {
      setError("Network error while signing in. Please try again.");
    } finally {
      setLoading(false);
    }
  }

  async function handleSignup(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSuccessMsg(null);
    setLoading(true);
    try {
      const res = await fetch("/api/auth/signup", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          fullName: fullName.trim(),
          email: email.trim(),
          phone: phone.trim(),
          country: country.trim() || "India",
          password,
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setError(data.error || "Unable to create account.");
        return;
      }
      router.push(data.redirectTo || "/dashboard");
      router.refresh();
    } catch {
      setError("Network error while creating your account.");
    } finally {
      setLoading(false);
    }
  }

  async function handleForgot(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSuccessMsg(null);
    setLoading(true);
    try {
      const res = await fetch("/api/auth/password-reset", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: email.trim() }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setError(data.error || "Unable to request password reset.");
        return;
      }
      setSuccessMsg(
        data.message ||
          "If an account exists for that email, password reset instructions have been queued."
      );
    } catch {
      setError("Network error while requesting password reset.");
    } finally {
      setLoading(false);
    }
  }

  async function handleResetComplete(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSuccessMsg(null);
    setLoading(true);
    try {
      const res = await fetch("/api/auth/password-reset", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          token: resetToken.trim(),
          newPassword,
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setError(data.error || "Unable to reset password.");
        return;
      }
      setSuccessMsg(
        data.message || "Password updated. You may now sign in with your new password."
      );
      setNewPassword("");
      setMode("login");
    } catch {
      setError("Network error while updating password.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen editorial-hero-bg text-[#F9F7FC] flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full mx-auto">
        <div className="mb-6 flex items-center justify-between">
          <Link
            href="/"
            className="inline-flex items-center gap-2 text-sm font-medium text-[#D7CCE2] hover:text-[#F9F7FC] touch-target"
          >
            <ArrowLeft className="w-4 h-4" aria-hidden="true" />
            Back to Festival Home
          </Link>
          <Link
            href="/apply"
            className="text-sm font-semibold text-[#D5B77B] hover:underline touch-target inline-flex items-center"
          >
            Register Now
          </Link>
        </div>

        <div className="surface-card rounded-2xl bg-[#25212B]/95 border border-[#383242] shadow-2xl p-6 sm:p-8 text-[#F9F7FC]">
          <div className="mb-6">
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[#D5B77B]">
              Purple Festival Gujarat 2027
            </p>
            <h1 className="mt-1 font-display text-2xl sm:text-3xl font-semibold text-[#F9F7FC]">
              {mode === "login" && "Sign In to Your Portal"}
              {mode === "signup" && "Create Participant Account"}
              {mode === "forgot" && "Reset Your Password"}
              {mode === "reset" && "Set a New Password"}
            </h1>
            <p className="mt-2 text-sm text-[#D7CCE2]">
              {mode === "login" &&
                "Participants, Delegates, Volunteers, SLI Professionals, and Authorized Festival Staff sign in here."}
              {mode === "signup" &&
                "Use one account to manage all your registrations, accommodations, and digital QR passes."}
              {mode === "forgot" &&
                "Enter your registered email address and we will queue a secure password reset link."}
              {mode === "reset" &&
                "Choose a strong new password of at least 8 characters."}
            </p>
          </div>

          {(mode === "login" || mode === "signup") && (
            <div
              role="tablist"
              aria-label="Account access mode"
              className="grid grid-cols-2 gap-1.5 p-1 rounded-xl bg-[#17151C] border border-[#383242] mb-6"
            >
              <button
                type="button"
                role="tab"
                aria-selected={mode === "login"}
                onClick={() => switchMode("login")}
                className={`min-h-[44px] rounded-lg text-sm font-semibold transition-colors ${
                  mode === "login"
                    ? "bg-[#9273B5] text-[#17151C]"
                    : "text-[#D7CCE2] hover:text-[#F9F7FC]"
                }`}
              >
                Sign In
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={mode === "signup"}
                onClick={() => switchMode("signup")}
                className={`min-h-[44px] rounded-lg text-sm font-semibold transition-colors ${
                  mode === "signup"
                    ? "bg-[#9273B5] text-[#17151C]"
                    : "text-[#D7CCE2] hover:text-[#F9F7FC]"
                }`}
              >
                Create Account
              </button>
            </div>
          )}

          {error && (
            <div
              role="alert"
              className="mb-5 rounded-xl bg-[#B42332]/20 border border-[#F07D87]/60 p-4 flex items-start gap-3 text-sm text-[#FFD9DD]"
            >
              <AlertCircle className="w-5 h-5 shrink-0 mt-0.5 text-[#FF99A3]" aria-hidden="true" />
              <span>{error}</span>
            </div>
          )}

          {successMsg && (
            <div
              role="status"
              aria-live="polite"
              className="mb-5 rounded-xl bg-[#19734B]/25 border border-[#56C28E]/60 p-4 flex items-start gap-3 text-sm text-[#D7F7E7]"
            >
              <CheckCircle2 className="w-5 h-5 shrink-0 mt-0.5 text-[#7DE2B2]" aria-hidden="true" />
              <span>{successMsg}</span>
            </div>
          )}

          {mode === "login" && (
            <form onSubmit={handleLogin} className="space-y-4" noValidate>
              <div>
                <label
                  htmlFor="login-email"
                  className="block text-sm font-medium text-[#E8DFF0] mb-1.5"
                >
                  Email Address
                </label>
                <div className="relative">
                  <Mail
                    className="w-4 h-4 text-[#B6A1C8] absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                    aria-hidden="true"
                  />
                  <input
                    id="login-email"
                    name="email"
                    type="email"
                    required
                    autoComplete="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="you@example.org"
                    className="w-full min-h-[44px] pl-10 pr-4 py-2.5 rounded-xl bg-[#17151C] border border-[#383242] text-[#F9F7FC] placeholder-[#8E829D] focus:border-[#D5B77B]"
                  />
                </div>
              </div>

              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <label
                    htmlFor="login-password"
                    className="block text-sm font-medium text-[#E8DFF0]"
                  >
                    Password
                  </label>
                  <button
                    type="button"
                    onClick={() => switchMode("forgot")}
                    className="text-xs font-medium text-[#D5B77B] hover:underline"
                  >
                    Forgot password?
                  </button>
                </div>
                <div className="relative">
                  <Lock
                    className="w-4 h-4 text-[#B6A1C8] absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                    aria-hidden="true"
                  />
                  <input
                    id="login-password"
                    name="password"
                    type={showPassword ? "text" : "password"}
                    required
                    autoComplete="current-password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Enter your password"
                    className="w-full min-h-[44px] pl-10 pr-12 py-2.5 rounded-xl bg-[#17151C] border border-[#383242] text-[#F9F7FC] placeholder-[#8E829D] focus:border-[#D5B77B]"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((v) => !v)}
                    aria-label={showPassword ? "Hide password" : "Show password"}
                    aria-pressed={showPassword}
                    className="absolute right-1.5 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center text-[#B6A1C8] hover:text-[#F9F7FC] rounded-lg"
                  >
                    {showPassword ? (
                      <EyeOff className="w-4 h-4" aria-hidden="true" />
                    ) : (
                      <Eye className="w-4 h-4" aria-hidden="true" />
                    )}
                  </button>
                </div>
              </div>

              {mfaRequired && (
                <div className="rounded-xl bg-[#17151C] border border-[#9273B5] p-4 space-y-2">
                  <label
                    htmlFor="login-mfa"
                    className="flex items-center gap-2 text-sm font-semibold text-[#E8DFF0]"
                  >
                    <ShieldCheck className="w-4 h-4 text-[#D5B77B]" aria-hidden="true" />
                    6-Digit Authenticator Code (MFA)
                  </label>
                  <input
                    id="login-mfa"
                    name="mfaCode"
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    required
                    maxLength={8}
                    value={mfaCode}
                    onChange={(e) => setMfaCode(e.target.value)}
                    placeholder="000 000"
                    className="w-full min-h-[44px] px-4 py-2.5 rounded-lg bg-[#25212B] border border-[#383242] text-[#F9F7FC] tracking-[0.25em] text-center font-mono text-lg"
                  />
                </div>
              )}

              <button
                type="submit"
                disabled={loading}
                className="btn-primary-pfg w-full min-h-[48px] rounded-xl bg-[#9273B5] hover:bg-[#A58DBB] text-[#17151C] font-bold text-base transition-colors disabled:opacity-60 flex items-center justify-center gap-2"
              >
                <KeyRound className="w-4 h-4" aria-hidden="true" />
                {loading
                  ? "Signing in..."
                  : mfaRequired
                    ? "Verify Code & Sign In"
                    : "Sign In"}
              </button>
            </form>
          )}

          {mode === "signup" && (
            <form onSubmit={handleSignup} className="space-y-4" noValidate>
              <div>
                <label
                  htmlFor="signup-name"
                  className="block text-sm font-medium text-[#E8DFF0] mb-1.5"
                >
                  Full Name
                </label>
                <input
                  id="signup-name"
                  type="text"
                  required
                  autoComplete="name"
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  placeholder="Aarav Patel"
                  className="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-[#17151C] border border-[#383242] text-[#F9F7FC] placeholder-[#8E829D]"
                />
              </div>

              <div>
                <label
                  htmlFor="signup-email"
                  className="block text-sm font-medium text-[#E8DFF0] mb-1.5"
                >
                  Email Address
                </label>
                <input
                  id="signup-email"
                  type="email"
                  required
                  autoComplete="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="you@example.org"
                  className="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-[#17151C] border border-[#383242] text-[#F9F7FC] placeholder-[#8E829D]"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label
                    htmlFor="signup-phone"
                    className="block text-sm font-medium text-[#E8DFF0] mb-1.5"
                  >
                    Phone (Optional)
                  </label>
                  <input
                    id="signup-phone"
                    type="tel"
                    autoComplete="tel"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    placeholder="+91 98250 12345"
                    className="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-[#17151C] border border-[#383242] text-[#F9F7FC] placeholder-[#8E829D]"
                  />
                </div>
                <div>
                  <label
                    htmlFor="signup-country"
                    className="block text-sm font-medium text-[#E8DFF0] mb-1.5"
                  >
                    Country
                  </label>
                  <input
                    id="signup-country"
                    type="text"
                    autoComplete="country-name"
                    value={country}
                    onChange={(e) => setCountry(e.target.value)}
                    className="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-[#17151C] border border-[#383242] text-[#F9F7FC]"
                  />
                </div>
              </div>

              <div>
                <label
                  htmlFor="signup-password"
                  className="block text-sm font-medium text-[#E8DFF0] mb-1.5"
                >
                  Password (minimum 8 characters)
                </label>
                <div className="relative">
                  <input
                    id="signup-password"
                    type={showPassword ? "text" : "password"}
                    required
                    minLength={8}
                    autoComplete="new-password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Create a strong password"
                    className="w-full min-h-[44px] pl-4 pr-12 py-2.5 rounded-xl bg-[#17151C] border border-[#383242] text-[#F9F7FC] placeholder-[#8E829D]"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((v) => !v)}
                    aria-label={showPassword ? "Hide password" : "Show password"}
                    aria-pressed={showPassword}
                    className="absolute right-1.5 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center text-[#B6A1C8] hover:text-[#F9F7FC]"
                  >
                    {showPassword ? (
                      <EyeOff className="w-4 h-4" aria-hidden="true" />
                    ) : (
                      <Eye className="w-4 h-4" aria-hidden="true" />
                    )}
                  </button>
                </div>
              </div>

              <button
                type="submit"
                disabled={loading}
                className="btn-primary-pfg w-full min-h-[48px] rounded-xl bg-[#9273B5] hover:bg-[#A58DBB] text-[#17151C] font-bold text-base transition-colors disabled:opacity-60 flex items-center justify-center gap-2"
              >
                <UserPlus className="w-4 h-4" aria-hidden="true" />
                {loading ? "Creating Account..." : "Create Participant Account"}
              </button>
            </form>
          )}

          {mode === "forgot" && (
            <form onSubmit={handleForgot} className="space-y-4" noValidate>
              <div>
                <label
                  htmlFor="forgot-email"
                  className="block text-sm font-medium text-[#E8DFF0] mb-1.5"
                >
                  Registered Email Address
                </label>
                <input
                  id="forgot-email"
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="you@example.org"
                  className="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-[#17151C] border border-[#383242] text-[#F9F7FC] placeholder-[#8E829D]"
                />
              </div>

              <button
                type="submit"
                disabled={loading}
                className="btn-primary-pfg w-full min-h-[48px] rounded-xl bg-[#9273B5] hover:bg-[#A58DBB] text-[#17151C] font-bold text-base transition-colors disabled:opacity-60"
              >
                {loading ? "Sending Request..." : "Request Password Reset"}
              </button>

              <button
                type="button"
                onClick={() => switchMode("login")}
                className="w-full min-h-[44px] text-sm font-medium text-[#D7CCE2] hover:text-[#F9F7FC]"
              >
                Back to Sign In
              </button>
            </form>
          )}

          {mode === "reset" && (
            <form onSubmit={handleResetComplete} className="space-y-4" noValidate>
              <div>
                <label
                  htmlFor="reset-token"
                  className="block text-sm font-medium text-[#E8DFF0] mb-1.5"
                >
                  Reset Token
                </label>
                <input
                  id="reset-token"
                  type="text"
                  required
                  value={resetToken}
                  onChange={(e) => setResetToken(e.target.value)}
                  className="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-[#17151C] border border-[#383242] text-[#F9F7FC] font-mono text-sm"
                />
              </div>

              <div>
                <label
                  htmlFor="reset-new-password"
                  className="block text-sm font-medium text-[#E8DFF0] mb-1.5"
                >
                  New Password (minimum 8 characters)
                </label>
                <input
                  id="reset-new-password"
                  type={showPassword ? "text" : "password"}
                  required
                  minLength={8}
                  autoComplete="new-password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  className="w-full min-h-[44px] px-4 py-2.5 rounded-xl bg-[#17151C] border border-[#383242] text-[#F9F7FC]"
                />
              </div>

              <button
                type="submit"
                disabled={loading}
                className="btn-primary-pfg w-full min-h-[48px] rounded-xl bg-[#9273B5] hover:bg-[#A58DBB] text-[#17151C] font-bold text-base transition-colors disabled:opacity-60"
              >
                {loading ? "Updating Password..." : "Update Password"}
              </button>

              <button
                type="button"
                onClick={() => switchMode("login")}
                className="w-full min-h-[44px] text-sm font-medium text-[#D7CCE2] hover:text-[#F9F7FC]"
              >
                Back to Sign In
              </button>
            </form>
          )}

          <div className="mt-6 pt-5 border-t border-[#383242] text-xs text-[#D7CCE2] flex flex-col gap-1.5">
            <p>
              Staff accounts (Admin, Superadmin, and Reception Desk RGST) are
              automatically routed to their authorized portal upon sign-in.
            </p>
            <p>
              Need accessibility assistance? Email{" "}
              <a
                href="mailto:purplefestivalvadodara@gmail.com"
                className="text-[#D5B77B] underline"
              >
                purplefestivalvadodara@gmail.com
              </a>
              .
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen editorial-hero-bg flex items-center justify-center text-[#F9F7FC]">
          Loading sign-in portal...
        </div>
      }
    >
      <LoginPortalContent />
    </Suspense>
  );
}

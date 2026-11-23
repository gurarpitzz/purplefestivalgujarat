"use client";

import React, { Suspense, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import {
  AlertCircle,
  CheckCircle2,
  Eye,
  EyeOff,
  ShieldCheck,
} from "lucide-react";

type AuthMode = "login" | "signup" | "forgot" | "reset";

const SPONSORS = [
  {
    name: "Purple Festival Gujarat 2027",
    src: "/assets/logos/purple-festival.jpg",
  },
  {
    name: "Brotherhood",
    src: "/assets/logos/brotherhood.jpg",
  },
  {
    name: "Global Inclusive Education Network (GIEN)",
    src: "/assets/logos/gien.jpg",
  },
  {
    name: "Praangan",
    src: "/assets/logos/praangan.jpg",
  },
] as const;

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

  const inputClass =
    "w-full min-h-[46px] rounded-lg border border-[#DCE5F7] bg-[#EEF4FF] px-4 py-2.5 text-[15px] text-[#202D59] placeholder-[#64748B] transition-colors focus:border-[#202D59] focus:bg-white focus:outline-none";

  return (
    <main
      id="main-content"
      tabIndex={-1}
      className="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-[#16215C] bg-cover bg-center px-4 py-10 sm:px-8 focus:outline-none"
      style={{
        backgroundImage: "url('/assets/login-bg.webp')",
      }}
    >
      <div className="relative z-10 mx-auto grid w-full max-w-5xl items-center gap-10 lg:grid-cols-12 lg:gap-12">
        {/* Left: Clean Floating White Organiser / Sponsor Strip */}
        <section
          aria-label="Festival Organisers and Partners"
          className="order-2 flex justify-center lg:order-1 lg:col-span-6 lg:justify-start"
        >
          <div className="w-full max-w-[540px] rounded-2xl bg-white/95 px-5 py-5 shadow-[0_24px_60px_rgba(12,18,48,0.28)] backdrop-blur-sm sm:px-7 sm:py-6">
            <div className="grid grid-cols-2 items-center gap-y-4 divide-slate-200 sm:grid-cols-4 sm:divide-x">
              {SPONSORS.map((sponsor) => (
                <div
                  key={sponsor.name}
                  className="flex h-16 items-center justify-center px-3"
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={sponsor.src}
                    alt={sponsor.name}
                    width={120}
                    height={56}
                    className="max-h-13 w-auto object-contain"
                  />
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Right: Clean Floating White Sign-In Card */}
        <section
          aria-labelledby="login-heading"
          className="order-1 flex justify-center lg:order-2 lg:col-span-6 lg:justify-end"
        >
          <div className="w-full max-w-[400px] rounded-2xl bg-white p-8 text-[#202D59] shadow-[0_24px_60px_rgba(12,18,48,0.3)] sm:p-10">
            <div className="mb-6 text-center">
              <h1
                id="login-heading"
                className="text-2xl font-bold tracking-[-0.02em] text-[#111827] sm:text-[26px]"
              >
                {mode === "login" && "Sign In"}
                {mode === "signup" && "Create Account"}
                {mode === "forgot" && "Reset Password"}
                {mode === "reset" && "Set New Password"}
              </h1>
              <p className="mt-1 text-xs font-medium text-[#64748B]">
                {mode === "login" && "With Your Credentials"}
                {mode === "signup" && "Purple Festival Gujarat 2027 Portal"}
                {mode === "forgot" && "Enter your registered email address"}
                {mode === "reset" && "Choose a new password (min. 8 characters)"}
              </p>
            </div>

            {error && (
              <div
                role="alert"
                className="mb-4 flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 p-3 text-xs font-medium text-red-800"
              >
                <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-red-600" aria-hidden="true" />
                <span>{error}</span>
              </div>
            )}

            {successMsg && (
              <div
                role="status"
                aria-live="polite"
                className="mb-4 flex items-start gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs font-medium text-emerald-800"
              >
                <CheckCircle2
                  className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"
                  aria-hidden="true"
                />
                <span>{successMsg}</span>
              </div>
            )}

            {mode === "login" && (
              <form onSubmit={handleLogin} className="space-y-4" noValidate>
                <div>
                  <label htmlFor="login-email" className="sr-only">
                    Email Address
                  </label>
                  <input
                    id="login-email"
                    name="email"
                    type="email"
                    required
                    autoComplete="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="Email address"
                    className={inputClass}
                  />
                </div>

                <div className="relative">
                  <label htmlFor="login-password" className="sr-only">
                    Password
                  </label>
                  <input
                    id="login-password"
                    name="password"
                    type={showPassword ? "text" : "password"}
                    required
                    autoComplete="current-password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Password"
                    className={`${inputClass} pr-11`}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((v) => !v)}
                    aria-label={showPassword ? "Hide password" : "Show password"}
                    aria-pressed={showPassword}
                    className="absolute right-1.5 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-md text-[#64748B] hover:text-[#202D59]"
                  >
                    {showPassword ? (
                      <EyeOff className="h-4 w-4" aria-hidden="true" />
                    ) : (
                      <Eye className="h-4 w-4" aria-hidden="true" />
                    )}
                  </button>
                </div>

                {mfaRequired && (
                  <div className="space-y-1.5 rounded-lg border border-[#A459FF]/40 bg-[#F5F0FF] p-3">
                    <label
                      htmlFor="login-mfa"
                      className="flex items-center gap-1.5 text-xs font-semibold text-[#202D59]"
                    >
                      <ShieldCheck className="h-4 w-4 text-[#A459FF]" aria-hidden="true" />
                      6-Digit Authenticator Code
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
                      className="w-full min-h-[42px] rounded-md border border-[#DCE5F7] bg-white px-3 text-center font-mono text-base tracking-[0.25em] text-[#202D59]"
                    />
                  </div>
                )}

                <button
                  type="submit"
                  disabled={loading}
                  className="btn-primary-pfg w-full min-h-[46px] rounded-lg bg-[#A459FF] text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-60"
                >
                  {loading
                    ? "Signing in..."
                    : mfaRequired
                      ? "Verify & Login"
                      : "Login"}
                </button>

                <div className="flex items-center justify-between pt-1 text-xs font-medium text-[#64748B]">
                  <button
                    type="button"
                    onClick={() => switchMode("signup")}
                    className="hover:text-[#202D59] hover:underline"
                  >
                    Create Account
                  </button>
                  <button
                    type="button"
                    onClick={() => switchMode("forgot")}
                    className="hover:text-[#202D59] hover:underline"
                  >
                    Forgot password?
                  </button>
                </div>
              </form>
            )}

            {mode === "signup" && (
              <form onSubmit={handleSignup} className="space-y-3.5" noValidate>
                <div>
                  <label htmlFor="signup-name" className="sr-only">
                    Full Name
                  </label>
                  <input
                    id="signup-name"
                    type="text"
                    required
                    autoComplete="name"
                    value={fullName}
                    onChange={(e) => setFullName(e.target.value)}
                    placeholder="Full Name"
                    className={inputClass}
                  />
                </div>
                <div>
                  <label htmlFor="signup-email" className="sr-only">
                    Email Address
                  </label>
                  <input
                    id="signup-email"
                    type="email"
                    required
                    autoComplete="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="Email address"
                    className={inputClass}
                  />
                </div>
                <div className="grid grid-cols-2 gap-2.5">
                  <div>
                    <label htmlFor="signup-phone" className="sr-only">
                      Phone
                    </label>
                    <input
                      id="signup-phone"
                      type="tel"
                      autoComplete="tel"
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      placeholder="Phone"
                      className={inputClass}
                    />
                  </div>
                  <div>
                    <label htmlFor="signup-country" className="sr-only">
                      Country
                    </label>
                    <input
                      id="signup-country"
                      type="text"
                      autoComplete="country-name"
                      value={country}
                      onChange={(e) => setCountry(e.target.value)}
                      placeholder="Country"
                      className={inputClass}
                    />
                  </div>
                </div>
                <div className="relative">
                  <label htmlFor="signup-password" className="sr-only">
                    Password
                  </label>
                  <input
                    id="signup-password"
                    type={showPassword ? "text" : "password"}
                    required
                    minLength={8}
                    autoComplete="new-password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Password (min. 8 chars)"
                    className={`${inputClass} pr-11`}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((v) => !v)}
                    aria-label={showPassword ? "Hide password" : "Show password"}
                    aria-pressed={showPassword}
                    className="absolute right-1.5 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-md text-[#64748B] hover:text-[#202D59]"
                  >
                    {showPassword ? (
                      <EyeOff className="h-4 w-4" aria-hidden="true" />
                    ) : (
                      <Eye className="h-4 w-4" aria-hidden="true" />
                    )}
                  </button>
                </div>

                <button
                  type="submit"
                  disabled={loading}
                  className="btn-primary-pfg w-full min-h-[46px] rounded-lg bg-[#A459FF] text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-60"
                >
                  {loading ? "Creating Account..." : "Create Account"}
                </button>

                <div className="text-center text-xs font-medium text-[#64748B]">
                  <button
                    type="button"
                    onClick={() => switchMode("login")}
                    className="hover:text-[#202D59] hover:underline"
                  >
                    Already have an account? Sign In
                  </button>
                </div>
              </form>
            )}

            {mode === "forgot" && (
              <form onSubmit={handleForgot} className="space-y-4" noValidate>
                <div>
                  <label htmlFor="forgot-email" className="sr-only">
                    Registered Email Address
                  </label>
                  <input
                    id="forgot-email"
                    type="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="Registered email address"
                    className={inputClass}
                  />
                </div>
                <button
                  type="submit"
                  disabled={loading}
                  className="btn-primary-pfg w-full min-h-[46px] rounded-lg bg-[#A459FF] text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-60"
                >
                  {loading ? "Sending..." : "Request Reset"}
                </button>
                <div className="text-center text-xs font-medium text-[#64748B]">
                  <button
                    type="button"
                    onClick={() => switchMode("login")}
                    className="hover:text-[#202D59] hover:underline"
                  >
                    Back to Sign In
                  </button>
                </div>
              </form>
            )}

            {mode === "reset" && (
              <form onSubmit={handleResetComplete} className="space-y-4" noValidate>
                <div>
                  <label htmlFor="reset-token" className="sr-only">
                    Reset Token
                  </label>
                  <input
                    id="reset-token"
                    type="text"
                    required
                    value={resetToken}
                    onChange={(e) => setResetToken(e.target.value)}
                    placeholder="Reset token"
                    className={`${inputClass} font-mono text-sm`}
                  />
                </div>
                <div>
                  <label htmlFor="reset-new-password" className="sr-only">
                    New Password
                  </label>
                  <input
                    id="reset-new-password"
                    type={showPassword ? "text" : "password"}
                    required
                    minLength={8}
                    autoComplete="new-password"
                    value={newPassword}
                    onChange={(e) => setNewPassword(e.target.value)}
                    placeholder="New password (min. 8 chars)"
                    className={inputClass}
                  />
                </div>
                <button
                  type="submit"
                  disabled={loading}
                  className="btn-primary-pfg w-full min-h-[46px] rounded-lg bg-[#A459FF] text-[15px] font-semibold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-60"
                >
                  {loading ? "Updating..." : "Update Password"}
                </button>
              </form>
            )}

            <div className="mt-6 text-center">
              <Link
                href="/"
                className="text-sm font-medium text-[#A459FF] transition-colors hover:text-[#202D59] hover:underline"
              >
                Return to Home Page
              </Link>
            </div>
          </div>
        </section>
      </div>
    </main>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center bg-[#16215C] text-white">
          Loading sign-in portal...
        </div>
      }
    >
      <LoginPortalContent />
    </Suspense>
  );
}

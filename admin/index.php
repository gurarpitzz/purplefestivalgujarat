<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin & Superadmin Operations Portal (/admin)
 * Exact visual and functional port of src/app/admin/page.tsx & src/components/AdminPortal.tsx.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = getCurrentUser();

if (!$user) {
    header('Location: /login?next=' . rawurlencode('/admin'));
    exit;
}

if (!hasRole($user, ['SUPERADMIN', 'ADMIN'])) {
    $roles = $user['roles'] ?? [];
    if (is_array($roles) && in_array('RGST', $roles, true)) {
        header('Location: /rgst');
        exit;
    }
    header('Location: /dashboard');
    exit;
}

$isSuperadmin = in_array('SUPERADMIN', $user['roles'] ?? [], true);

$currentUserPayload = [
    'id'                    => (string)$user['id'],
    'fullName'              => (string)$user['fullName'],
    'email'                 => (string)$user['email'],
    'roles'                 => array_values($user['roles'] ?? []),
    'permissions'           => array_values($user['permissions'] ?? []),
    'mfaEnabled'            => !empty($user['mfaEnabled']),
    'mfaVerified'           => !empty($user['mfaVerified']),
    'mfaRequiredForRole'    => !empty($user['mfaRequiredForRole']),
    'mfaEnrollmentRequired' => !empty($user['mfaEnrollmentRequired']),
];

function h_admin(string $val): string
{
    return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" data-text-scale="100" data-contrast="default" data-reduced-motion="false">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#15121B" />
  <title>Admin Portal — Purple Festival Gujarat 2027</title>
  <meta name="description" content="Administrative operations, volunteer &amp; SLI review, accessibility coordination, CMS, and attendance analytics for Purple Festival Gujarat 2027." />
  <meta name="robots" content="noindex, nofollow" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..800;1,9..40,400..800&display=swap" />
  <link rel="stylesheet" href="/assets/css/app.css" />
</head>
<body class="min-h-screen bg-[#F6F4FA] text-[#17151C] antialiased">
  <a
    href="#admin-main-content"
    class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:bg-[#6B4E96] focus:text-white focus:px-4 focus:py-2 focus:rounded-lg"
  >
    Skip to main content
  </a>

  <div class="min-h-screen bg-[#F6F4FA] text-[#17151C] flex">
    <!-- Mobile Sidebar Backdrop -->
    <div
      id="admin-sidebar-backdrop"
      class="fixed inset-0 z-30 bg-black/60 hidden lg:hidden"
      aria-hidden="true"
    ></div>

    <!-- Charcoal-Black Sidebar with Dusty-Purple Highlights -->
    <aside
      id="admin-sidebar"
      aria-label="Admin navigation"
      class="fixed lg:sticky top-0 left-0 z-40 h-screen w-68 shrink-0 bg-[#15121B] text-[#F2EFF8] border-r border-[#2B2538] flex flex-col transition-transform duration-200 -translate-x-full lg:translate-x-0"
    >
      <div class="px-5 py-5 border-b border-[#2B2538] flex items-center justify-between">
        <div>
          <p class="text-[11px] uppercase tracking-[0.2em] text-[#B79CE0] font-semibold">
            Purple Festival 2027
          </p>
          <h1 class="text-base font-bold text-white mt-0.5">
            <?= $isSuperadmin ? 'Superadmin Portal' : 'Admin Operations' ?>
          </h1>
        </div>
        <button
          id="admin-sidebar-close"
          type="button"
          class="lg:hidden p-1.5 rounded-md text-[#B79CE0] hover:bg-white/10"
          aria-label="Close navigation menu"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>

      <nav id="admin-nav-list" class="flex-1 overflow-y-auto py-4 px-3 space-y-1" aria-label="Portal sections">
        <!-- Populated dynamically by admin-portal.js -->
      </nav>

      <div class="p-4 border-t border-[#2B2538] space-y-3 bg-[#110E16]">
        <div class="flex items-center gap-2">
          <a
            href="/rgst"
            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium bg-[#272133] hover:bg-[#342C45] text-[#E6DCF7] transition-colors"
          >
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>
            RGST Desk
          </a>
          <a
            href="/"
            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium bg-[#272133] hover:bg-[#342C45] text-[#E6DCF7] transition-colors"
          >
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
            Public Site
          </a>
        </div>
        <div class="flex items-center justify-between pt-2 border-t border-[#262033]">
          <div class="min-w-0">
            <p class="text-xs font-semibold text-white truncate"><?= h_admin($currentUserPayload['fullName']) ?></p>
            <p class="text-[11px] text-[#9E93B5] truncate"><?= h_admin($currentUserPayload['email']) ?></p>
          </div>
          <button
            id="admin-logout-btn"
            type="button"
            class="p-2 rounded-lg text-[#C4BCD4] hover:text-white hover:bg-rose-500/20 transition-colors"
            title="Sign out"
            aria-label="Sign out"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 min-w-0 flex flex-col">
      <!-- Top Bar -->
      <header class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-[#E5DFEF] px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <button
            id="admin-sidebar-open"
            type="button"
            class="lg:hidden p-2 rounded-lg border border-[#DDD5EA] text-[#3B2E52] hover:bg-[#F3EEF9]"
            aria-label="Open navigation menu"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
          </button>
          <div>
            <h2 id="admin-active-tab-title" class="text-lg font-bold text-[#1B1724]">Overview &amp; Analytics</h2>
            <p class="text-xs text-[#645C75]">
              Vadodara, Gujarat • 21–23 January 2027 • Live Database Telemetry
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#EFE9F8] text-[#563A80] border border-[#DDD1F0]">
            <?= h_admin(implode(' / ', $currentUserPayload['roles'])) ?>
          </span>
          <button
            id="admin-refresh-btn"
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border border-[#DDD5EA] bg-white hover:bg-[#F5F1FA] text-[#3B2E52]"
          >
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
            Refresh
          </button>
        </div>
      </header>

      <!-- Live Toast Notification Region -->
      <div id="admin-toast-region" aria-live="polite" class="px-4 sm:px-6 lg:px-8 pt-3"></div>

      <!-- Dynamic Tab Content Container -->
      <main id="admin-main-content" class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6"></main>
    </div>
  </div>

  <!-- Slide-Over Modal & Dialog Root -->
  <div id="admin-modal-root"></div>

  <script>
    window.PFG_ADMIN_USER = <?= pfgSafeJsonForScript($currentUserPayload) ?>;
    window.PFG_CSRF_TOKEN = <?= pfgSafeJsonForScript(getCsrfToken()) ?>;
  </script>
  <script src="/assets/js/qrcode-gen.js" defer></script>
  <script src="/assets/js/admin-portal.js" defer></script>
</body>
</html>

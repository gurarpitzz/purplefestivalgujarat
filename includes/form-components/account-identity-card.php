<?php
declare(strict_types=1);

/**
 * Reusable Form Component 1: Authenticated Account Identity Summary Card
 * Shared across /apply/attendee, /apply/volunteer, and /apply/sli.
 * Displays account Name, Verified Email, Phone, City/State cleanly to avoid duplicate typing.
 */

if (!function_exists('pfgRenderAccountIdentityCard')) {
    function pfgRenderAccountIdentityCard(array $accountProfile): string
    {
        $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        $fullName = trim((string)($accountProfile['full_name'] ?? $accountProfile['fullName'] ?? ''));
        $email = trim((string)($accountProfile['email'] ?? ''));
        $phone = trim((string)($accountProfile['phone'] ?? ''));
        $cityDistrict = trim((string)($accountProfile['city_district'] ?? $accountProfile['cityDistrict'] ?? ''));
        $state = trim((string)($accountProfile['state'] ?? ''));
        $country = trim((string)($accountProfile['country'] ?? 'India'));
        $preferredLang = trim((string)($accountProfile['preferred_language'] ?? $accountProfile['preferredLanguage'] ?? ''));
        $isVerified = !empty($accountProfile['email_verified_at']) || !empty($accountProfile['emailVerifiedAt']);

        $locationParts = array_values(array_filter([$cityDistrict, $state, $country]));
        $locationDisplay = !empty($locationParts) ? implode(', ', $locationParts) : 'India';

        ob_start();
        ?>
        <section
          aria-labelledby="account-identity-heading"
          class="rounded-2xl border-2 border-[#202D59]/15 bg-[#F6F5FE] p-5 sm:p-6"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">
                Authenticated Account Identity
              </p>
              <h2 id="account-identity-heading" class="mt-1 text-lg font-bold text-[#202D59]">
                Your Contact Profile
              </h2>
              <p class="mt-1 text-sm text-[#46537C]">
                Retrieved automatically from your verified account so you do not have to re-type your contact details.
              </p>
            </div>
            <a
              href="/dashboard#profile"
              class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-4 py-2 text-sm font-semibold text-[#202D59] transition-colors hover:bg-[#202D59] hover:text-white"
            >
              Edit Account Profile
            </a>
          </div>

          <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-[#DCE5F7] bg-white px-4 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wide text-[#46537C]">Full Name</dt>
              <dd class="mt-1 text-sm font-bold text-[#202D59]"><?= $esc($fullName !== '' ? $fullName : '—') ?></dd>
            </div>

            <div class="rounded-xl border border-[#DCE5F7] bg-white px-4 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wide text-[#46537C]">
                Email Address
                <?php if ($isVerified): ?>
                  <span class="ml-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-900">Verified</span>
                <?php endif; ?>
              </dt>
              <dd class="mt-1 break-all text-sm font-bold text-[#202D59]"><?= $esc($email !== '' ? $email : '—') ?></dd>
            </div>

            <div class="rounded-xl border border-[#DCE5F7] bg-white px-4 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wide text-[#46537C]">Mobile / WhatsApp</dt>
              <dd class="mt-1 text-sm font-bold text-[#202D59]"><?= $esc($phone !== '' ? $phone : 'Not added yet') ?></dd>
            </div>

            <div class="rounded-xl border border-[#DCE5F7] bg-white px-4 py-3 sm:col-span-2">
              <dt class="text-xs font-semibold uppercase tracking-wide text-[#46537C]">Location</dt>
              <dd class="mt-1 text-sm font-semibold text-[#202D59]"><?= $esc($locationDisplay) ?></dd>
            </div>

            <div class="rounded-xl border border-[#DCE5F7] bg-white px-4 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wide text-[#46537C]">Preferred Language</dt>
              <dd class="mt-1 text-sm font-semibold text-[#202D59]"><?= $esc($preferredLang !== '' ? $preferredLang : 'Not specified') ?></dd>
            </div>
          </dl>

          <?php if ($phone === ''): ?>
            <div class="mt-4 rounded-xl border-2 border-amber-500/50 bg-amber-50 p-4">
              <label for="pfg-account-phone-inline" class="block text-sm font-bold text-[#202D59]">
                Mobile / WhatsApp Number <span class="text-red-700" aria-hidden="true">*</span>
              </label>
              <p id="pfg-account-phone-help" class="mt-0.5 text-xs text-[#46537C]">
                Your account does not have a phone number yet. Enter one here to complete your registration (it will also be saved to your profile).
              </p>
              <input
                type="tel"
                id="pfg-account-phone-inline"
                name="phone"
                required
                autocomplete="tel"
                placeholder="+91 98250 12345"
                aria-describedby="pfg-account-phone-help"
                class="touch-target mt-2 w-full max-w-md rounded-lg border-2 border-[#202D59]/30 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              />
            </div>
          <?php else: ?>
            <input type="hidden" name="phone" value="<?= $esc($phone) ?>" />
          <?php endif; ?>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}

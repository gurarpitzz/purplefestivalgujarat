<?php
declare(strict_types=1);

/**
 * Reusable Form Component 5: Declarations & DPDP Act 2023 Consents Block
 * Shared across ATTENDEE, VOLUNTEER, and SLI.
 * All checkboxes start unchecked by default.
 */

if (!function_exists('pfgRenderConsentDeclarations')) {
    function pfgRenderConsentDeclarations(string $categoryCode = 'ATTENDEE', array $initialValues = []): string
    {
        $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $cat = strtoupper(trim($categoryCode));

        $chkZero = !empty($initialValues['consentZeroTolerance']);
        $chkDpdp = !empty($initialValues['consentDataProcessing']);
        $chkAcc = !empty($initialValues['consentAccuracy']);
        $chkMedia = !empty($initialValues['consentMediaPhotography']);
        $chkFuture = !empty($initialValues['consentFutureUpdates']);

        ob_start();
        ?>
        <section
          aria-labelledby="pfg-consents-heading"
          class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7"
        >
          <div class="mb-4">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Declarations &amp; Privacy</p>
            <h2 id="pfg-consents-heading" class="mt-1 text-xl font-bold text-[#202D59]">
              Code of Conduct &amp; DPDP Consents
            </h2>
            <p class="mt-1 text-sm text-[#46537C]">
              Please review and check the required declarations below before submitting.
            </p>
          </div>

          <fieldset class="space-y-3 border-0 p-0">
            <legend class="sr-only">Required and optional festival declarations</legend>

            <!-- Required 1: Zero-Tolerance / Code of Conduct -->
            <label
              for="pfg-consent-zero-tolerance"
              class="touch-target flex cursor-pointer items-start gap-3 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] p-4 text-sm text-[#202D59] hover:border-[#A459FF]"
            >
              <input
                type="checkbox"
                id="pfg-consent-zero-tolerance"
                name="consentZeroTolerance"
                value="1"
                required
                <?= $chkZero ? 'checked' : '' ?>
                class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
              />
              <span>
                <strong class="font-bold">Code of Conduct &amp; Zero-Tolerance Policy</strong>
                <span class="text-red-700" aria-hidden="true">*</span>
                <span class="mt-1 block text-xs leading-relaxed text-[#46537C]">
                  I agree to maintain an inclusive, respectful, dignified, and harassment-free environment aligned with Purple Festival Gujarat 2027 and its zero-tolerance policy toward discrimination or harassment.
                </span>
              </span>
            </label>

            <!-- Required 2: DPDP Act 2023 Data Processing -->
            <label
              for="pfg-consent-dpdp"
              class="touch-target flex cursor-pointer items-start gap-3 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] p-4 text-sm text-[#202D59] hover:border-[#A459FF]"
            >
              <input
                type="checkbox"
                id="pfg-consent-dpdp"
                name="consentDataProcessing"
                value="1"
                required
                <?= $chkDpdp ? 'checked' : '' ?>
                class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
              />
              <span>
                <strong class="font-bold">DPDP Act 2023 Data Processing Consent</strong>
                <span class="text-red-700" aria-hidden="true">*</span>
                <span class="mt-1 block text-xs leading-relaxed text-[#46537C]">
                  I consent to Purple Festival Gujarat 2027 processing my registration and optional accessibility details strictly for festival participation, safety, and accommodation coordination in accordance with the
                  <a href="/legal/privacy" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#5B2A86] underline">Privacy Notice</a>
                  and the Digital Personal Data Protection (DPDP) Act, 2023.
                </span>
              </span>
            </label>

            <!-- Required 3: Information Accuracy Declaration -->
            <label
              for="pfg-consent-accuracy"
              class="touch-target flex cursor-pointer items-start gap-3 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] p-4 text-sm text-[#202D59] hover:border-[#A459FF]"
            >
              <input
                type="checkbox"
                id="pfg-consent-accuracy"
                name="consentAccuracy"
                value="1"
                required
                <?= $chkAcc ? 'checked' : '' ?>
                class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
              />
              <span>
                <strong class="font-bold">Information Accuracy Declaration</strong>
                <span class="text-red-700" aria-hidden="true">*</span>
                <span class="mt-1 block text-xs leading-relaxed text-[#46537C]">
                  I confirm that the details submitted in this <?= $cat === 'ATTENDEE' ? 'registration' : 'application' ?> are true and accurate to the best of my knowledge.
                  <?php if ($cat !== 'ATTENDEE'): ?>
                    I understand that submitting an application does not guarantee approval or shift assignment until reviewed by festival coordinators.
                  <?php endif; ?>
                </span>
              </span>
            </label>

            <!-- Optional 4: Photography & Media -->
            <label
              for="pfg-consent-media"
              class="touch-target flex cursor-pointer items-start gap-3 rounded-xl border border-[#DCE5F7] bg-white p-4 text-sm text-[#202D59] hover:border-[#A459FF]"
            >
              <input
                type="checkbox"
                id="pfg-consent-media"
                name="consentMediaPhotography"
                value="1"
                <?= $chkMedia ? 'checked' : '' ?>
                class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
              />
              <span>
                <strong class="font-semibold">Photography &amp; Filming Consent</strong>
                <span class="ml-1 text-xs font-normal text-[#46537C]">(Optional)</span>
                <span class="mt-1 block text-xs leading-relaxed text-[#46537C]">
                  I grant permission for event photography and filming for non-commercial festival documentation and awareness. Declining this does not affect your participation.
                </span>
              </span>
            </label>

            <!-- Optional 5: Future Event Updates -->
            <label
              for="pfg-consent-future"
              class="touch-target flex cursor-pointer items-start gap-3 rounded-xl border border-[#DCE5F7] bg-white p-4 text-sm text-[#202D59] hover:border-[#A459FF]"
            >
              <input
                type="checkbox"
                id="pfg-consent-future"
                name="consentFutureUpdates"
                value="1"
                <?= $chkFuture ? 'checked' : '' ?>
                class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
              />
              <span>
                <strong class="font-semibold">Future Festival Updates Opt-in</strong>
                <span class="ml-1 text-xs font-normal text-[#46537C]">(Optional)</span>
                <span class="mt-1 block text-xs leading-relaxed text-[#46537C]">
                  I would like to receive announcements about future Purple Festival Gujarat editions and inclusion initiatives.
                </span>
              </span>
            </label>
          </fieldset>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}

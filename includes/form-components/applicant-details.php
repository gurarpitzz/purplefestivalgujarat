<?php
declare(strict_types=1);

/**
 * Reusable Form Component: Step 1 — Contact details (Accountless)
 * Shared across /apply/attendee, /apply/sli, and /apply/volunteer.
 * Includes accessible hidden honeypot anti-spam field (zero visual CAPTCHA).
 */

if (!function_exists('pfgRenderApplicantDetails')) {
    function pfgRenderApplicantDetails(bool $includeLocation = true, string $categoryLabel = 'Registration'): string
    {
        $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $languages = ['English', 'Gujarati', 'Hindi', 'Indian Sign Language (ISL)', 'Other'];
        $states = defined('PFG_INDIAN_STATES') ? PFG_INDIAN_STATES : ['Gujarat', 'Outside India'];

        ob_start();
        ?>
        <!-- Accessible anti-spam honeypot (hidden from visual users and assistive tech) -->
        <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
          <label for="pfg-hp-website">Leave this field empty</label>
          <input type="text" id="pfg-hp-website" name="websiteHp" tabindex="-1" autocomplete="off" value="" />
        </div>

        <section aria-labelledby="applicant-details-heading">
          <h2 id="applicant-details-heading" tabindex="-1" class="pfg-step-heading">
            Contact details
          </h2>

          <div class="grid gap-4 sm:grid-cols-2">
            <div data-field-wrap="fullName">
              <label for="pfg-full-name" class="pfg-field-label">
                Full name <span class="pfg-field-req" aria-hidden="true">*</span>
              </label>
              <input
                type="text"
                id="pfg-full-name"
                name="fullName"
                required
                minlength="2"
                maxlength="200"
                autocomplete="name"
                placeholder="Participant full name"
                class="pfg-input"
              />
            </div>

            <div data-field-wrap="email">
              <label for="pfg-email" class="pfg-field-label">
                Email address <span class="pfg-field-req" aria-hidden="true">*</span>
              </label>
              <input
                type="email"
                id="pfg-email"
                name="email"
                required
                maxlength="255"
                autocomplete="email"
                autocapitalize="none"
                spellcheck="false"
                placeholder="name@example.com"
                aria-describedby="pfg-email-help"
                class="pfg-input"
              />
              <p id="pfg-email-help" class="pfg-hint">
                We&rsquo;ll email your registration ID and updates here.
              </p>
            </div>
          </div>

          <div class="mt-4 grid items-end gap-4 sm:grid-cols-2">
            <div data-field-wrap="phone">
              <label for="pfg-phone" class="pfg-field-label">
                Mobile / WhatsApp number <span class="pfg-field-req" aria-hidden="true">*</span>
              </label>
              <input
                type="tel"
                id="pfg-phone"
                name="phone"
                required
                maxlength="20"
                autocomplete="tel"
                placeholder="+91 98250 12345"
                class="pfg-input"
              />
            </div>

            <div data-field-wrap="preferredLanguage">
              <label for="pfg-preferred-language" class="pfg-field-label">
                Preferred communication language <span class="pfg-field-opt">(optional)</span>
              </label>
              <select
                id="pfg-preferred-language"
                name="preferredLanguage"
                class="pfg-select"
              >
                <option value="">Select language</option>
                <?php foreach ($languages as $lang): ?>
                  <option value="<?= $esc($lang) ?>"><?= $esc($lang) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mt-4 grid items-end gap-4 <?= $includeLocation ? 'sm:grid-cols-3' : 'sm:grid-cols-2' ?>">
            <?php if ($includeLocation): ?>
              <div data-field-wrap="cityDistrict">
                <label for="pfg-city-district" class="pfg-field-label">
                  City / District <span class="pfg-field-opt">(optional)</span>
                </label>
                <input
                  type="text"
                  id="pfg-city-district"
                  name="cityDistrict"
                  maxlength="120"
                  autocomplete="address-level2"
                  placeholder="e.g., Vadodara"
                  class="pfg-input"
                />
              </div>

              <div data-field-wrap="state">
                <label for="pfg-state" class="pfg-field-label">
                  State / Union Territory <span class="pfg-field-opt">(optional)</span>
                </label>
                <select
                  id="pfg-state"
                  name="state"
                  class="pfg-select"
                >
                  <option value="">Select state / UT</option>
                  <?php foreach ($states as $st): ?>
                    <option value="<?= $esc($st) ?>"><?= $esc($st) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>

            <div data-field-wrap="country">
              <label for="pfg-country" class="pfg-field-label">
                Country <span class="pfg-field-req" aria-hidden="true">*</span>
              </label>
              <input
                type="text"
                id="pfg-country"
                name="country"
                required
                minlength="2"
                maxlength="100"
                autocomplete="country-name"
                value="India"
                class="pfg-input"
              />
            </div>
          </div>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}

if (!function_exists('pfgRenderRegistrationClosedCard')) {
    function pfgRenderRegistrationClosedCard(string $categoryTitle = 'Public Registration'): string
    {
        $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        ob_start();
        ?>
        <section
          role="status"
          aria-labelledby="pfg-reg-closed-heading"
          class="rounded-xl border border-[#5B2A86]/25 bg-[#FAF7FF] p-6"
        >
          <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
            Registration Currently Closed
          </span>
          <h2 id="pfg-reg-closed-heading" class="mt-3 text-xl font-bold text-[#202D59]">
            <?= $esc($categoryTitle) ?> Is Not Yet Open
          </h2>
          <p class="mt-2 text-sm leading-relaxed text-[#46537C]">
            Public registration for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara) is currently closed while final preparations are underway. Please check back soon or contact the Festival Secretariat at
            <a href="mailto:purplefestivalgujarat@gmail.com" class="font-semibold text-[#5B2A86] underline">purplefestivalgujarat@gmail.com</a>
            for announcements and accessibility inquiries.
          </p>
          <div class="mt-6 flex flex-wrap gap-3">
            <a href="/" class="touch-target inline-flex items-center justify-center rounded-lg bg-[#202D59] px-6 py-2.5 text-sm font-bold text-white hover:bg-[#5B2A86]">
              Return to Homepage
            </a>
            <a href="/programme" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-2.5 text-sm font-bold text-[#202D59] hover:bg-[#DDDDF9]">
              Explore Programme
            </a>
          </div>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}

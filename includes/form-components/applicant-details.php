<?php
declare(strict_types=1);

/**
 * Reusable Form Component: Accountless Applicant Personal & Contact Details
 * Shared across /apply/attendee, /apply/sli, and /apply/volunteer.
 * Replaces the retired authenticated account identity card (CAD-17).
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

        <section
          aria-labelledby="applicant-details-heading"
          class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7"
        >
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">
            Personal &amp; Contact Information · No Account Required
          </p>
          <h2 id="applicant-details-heading" class="mt-1 text-xl font-bold text-[#202D59]">
            Applicant Details
          </h2>
          <p class="mt-1 text-sm text-[#46537C]">
            Enter the participant&rsquo;s details directly below. No website account or password is needed — your official Registration ID and updates will be communicated via email.
          </p>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label for="pfg-full-name" class="block text-sm font-bold text-[#202D59]">
                Full Name <span class="text-red-700" aria-hidden="true">*</span>
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
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              />
            </div>

            <div>
              <label for="pfg-email" class="block text-sm font-bold text-[#202D59]">
                Email Address <span class="text-red-700" aria-hidden="true">*</span>
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
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              />
              <p id="pfg-email-help" class="mt-1 text-xs text-[#46537C]">
                Confirmation and official <?= $esc(strtolower($categoryLabel)) ?> updates are sent here. Caregivers or institutions may use a shared contact email for multiple participants.
              </p>
            </div>
          </div>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <label for="pfg-phone" class="block text-sm font-bold text-[#202D59]">
                Mobile / WhatsApp Number <span class="text-red-700" aria-hidden="true">*</span>
              </label>
              <input
                type="tel"
                id="pfg-phone"
                name="phone"
                required
                maxlength="20"
                autocomplete="tel"
                placeholder="+91 98250 12345"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              />
            </div>

            <div>
              <label for="pfg-preferred-language" class="block text-sm font-bold text-[#202D59]">
                Preferred Communication Language <span class="font-normal text-[#46537C]">(Optional)</span>
              </label>
              <select
                id="pfg-preferred-language"
                name="preferredLanguage"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              >
                <option value="">Select (Optional)</option>
                <?php foreach ($languages as $lang): ?>
                  <option value="<?= $esc($lang) ?>"><?= $esc($lang) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mt-4 grid gap-4 <?= $includeLocation ? 'sm:grid-cols-3' : 'sm:grid-cols-2' ?>">
            <?php if ($includeLocation): ?>
              <div>
                <label for="pfg-city-district" class="block text-sm font-bold text-[#202D59]">
                  City / District <span class="font-normal text-[#46537C]">(Optional)</span>
                </label>
                <input
                  type="text"
                  id="pfg-city-district"
                  name="cityDistrict"
                  maxlength="120"
                  autocomplete="address-level2"
                  placeholder="e.g., Vadodara"
                  class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
                />
              </div>

              <div>
                <label for="pfg-state" class="block text-sm font-bold text-[#202D59]">
                  State / Union Territory <span class="font-normal text-[#46537C]">(Optional)</span>
                </label>
                <select
                  id="pfg-state"
                  name="state"
                  class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
                >
                  <option value="">Select state / UT</option>
                  <?php foreach ($states as $st): ?>
                    <option value="<?= $esc($st) ?>"><?= $esc($st) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>

            <div>
              <label for="pfg-country" class="block text-sm font-bold text-[#202D59]">
                Country <span class="text-red-700" aria-hidden="true">*</span>
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
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
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
          class="rounded-2xl border-2 border-[#5B2A86]/30 bg-white p-6 sm:p-8 shadow-xs"
        >
          <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
            Registration Currently Closed
          </span>
          <h2 id="pfg-reg-closed-heading" class="mt-3 text-xl font-bold text-[#202D59]">
            <?= $esc($categoryTitle) ?> Is Not Yet Open
          </h2>
          <p class="mt-2 text-sm leading-relaxed text-[#46537C]">
            Public registration for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara) is currently closed while final preparations are underway. Please check back soon or contact the Festival Secretariat at
            <a href="mailto:purplefestivalvadodara@gmail.com" class="font-semibold text-[#5B2A86] underline">purplefestivalvadodara@gmail.com</a>
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


<?php
declare(strict_types=1);

/**
 * Reusable Form Component 4: Emergency Contact Block
 * Shared across ATTENDEE, VOLUNTEER, and SLI.
 * Required for Volunteer, SLI, and Under-18 Attendees; optional for adult Attendees.
 */

require_once dirname(__DIR__) . '/registration-validators.php';

if (!function_exists('pfgRenderEmergencyContact')) {
    function pfgRenderEmergencyContact(
        array $initialValues = [],
        bool $required = true,
        bool $relationshipRequired = true
    ): string {
        $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        $ecName = trim((string)($initialValues['emergencyContactName'] ?? $initialValues['emergency_contact_name'] ?? ''));
        $ecRel = trim((string)($initialValues['emergencyContactRelationship'] ?? $initialValues['emergency_contact_relationship'] ?? ''));
        $ecPhone = trim((string)($initialValues['emergencyContactPhone'] ?? $initialValues['emergency_contact_phone'] ?? ''));

        $reqAttr = $required ? 'required' : '';
        $relReqAttr = ($required && $relationshipRequired) ? 'required' : '';

        ob_start();
        ?>
        <section
          id="pfg-emergency-contact-section"
          data-emergency-required="<?= $required ? 'true' : 'false' ?>"
          aria-labelledby="pfg-emergency-heading"
          class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7"
        >
          <div class="mb-4">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Safety &amp; Emergency Contact</p>
            <h2 id="pfg-emergency-heading" class="mt-1 text-xl font-bold text-[#202D59]">
              Emergency Contact
              <span
                id="pfg-emergency-requirement-badge"
                class="ml-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold <?= $required ? 'bg-[#F2E9FE] text-[#5B2A86]' : 'bg-slate-100 text-[#46537C]' ?>"
              >
                <?= $required ? 'Required' : 'Optional for adult attendees' ?>
              </span>
            </h2>
            <p id="pfg-emergency-help" class="mt-1 text-sm text-[#46537C]">
              Used strictly for on-site safety or medical emergencies during Purple Festival Gujarat 2027. Never shown on public screens or check-in desks.
            </p>
          </div>

          <div class="grid gap-4 sm:grid-cols-3">
            <div>
              <label for="pfg-ec-name" class="block text-sm font-bold text-[#202D59]">
                Contact Full Name
                <span data-ec-star="true" class="text-red-700 <?= $required ? '' : 'hidden' ?>" aria-hidden="true">*</span>
              </label>
              <input
                type="text"
                id="pfg-ec-name"
                name="emergencyContactName"
                maxlength="150"
                value="<?= $esc($ecName) ?>"
                <?= $reqAttr ?>
                placeholder="Full name"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              />
            </div>

            <div>
              <label for="pfg-ec-relationship" class="block text-sm font-bold text-[#202D59]">
                Relationship
                <span data-ec-rel-star="true" class="text-red-700 <?= ($required && $relationshipRequired) ? '' : 'hidden' ?>" aria-hidden="true">*</span>
              </label>
              <select
                id="pfg-ec-relationship"
                name="emergencyContactRelationship"
                <?= $relReqAttr ?>
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              >
                <option value="">Select relationship</option>
                <?php foreach (PFG_EMERGENCY_RELATIONSHIPS as $rel): ?>
                  <option value="<?= $esc($rel) ?>" <?= $ecRel === $rel ? 'selected' : '' ?>><?= $esc($rel) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label for="pfg-ec-phone" class="block text-sm font-bold text-[#202D59]">
                Emergency Phone Number
                <span data-ec-star="true" class="text-red-700 <?= $required ? '' : 'hidden' ?>" aria-hidden="true">*</span>
              </label>
              <input
                type="tel"
                id="pfg-ec-phone"
                name="emergencyContactPhone"
                maxlength="20"
                value="<?= $esc($ecPhone) ?>"
                <?= $reqAttr ?>
                placeholder="+91 98250 12345"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              />
            </div>
          </div>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}

<?php
declare(strict_types=1);

/**
 * Reusable Form Component 2: Shared Accessibility & Accommodations Module (CAD-07)
 * Embedded identically across ATTENDEE, VOLUNTEER, and SLI forms.
 *
 * Guarantees:
 * - Strictly optional disability disclosure (YES / NO / PREFER_NOT_TO_SAY, visually unselected by default)
 * - Grouped 21 RPwD Act 2016 specified disabilities with accessible live search (focus-preserving)
 * - Accommodations checklist ALWAYS visible & selectable regardless of disability disclosure answer
 * - Optional Personal Assistant / Escort accompanying fields (separated from Under-18 legal guardian)
 */

if (!function_exists('pfgGetUmbrellaDisabilityGroups')) {
    function pfgGetUmbrellaDisabilityGroups(array $disabilityCategories): array
    {
        $groupOrder = [
            'Visual'                           => ['BLINDNESS', 'LOW_VISION'],
            'Hearing'                          => ['HEARING_IMPAIRMENT'],
            'Speech & Language'                => ['SPEECH_AND_LANGUAGE'],
            'Locomotor / Physical'             => ['LOCOMOTOR_DISABILITY', 'CEREBRAL_PALSY', 'MUSCULAR_DYSTROPHY', 'DWARFISM', 'LEPROSY_CURED', 'ACID_ATTACK_VICTIM'],
            'Neurodevelopmental / Intellectual' => ['AUTISM_SPECTRUM_DISORDER', 'SPECIFIC_LEARNING_DISABILITIES', 'INTELLECTUAL_DISABILITY'],
            'Mental / Psychosocial'            => ['MENTAL_ILLNESS'],
            'Chronic Neurological'             => ['CHRONIC_NEUROLOGICAL', 'PARKINSONS_DISEASE', 'MULTIPLE_SCLEROSIS'],
            'Blood Disorders'                  => ['THALASSEMIA', 'HEMOPHILIA', 'SICKLE_CELL_DISEASE'],
            'Multiple Disabilities'            => ['MULTIPLE_DISABILITIES'],
        ];

        $byCode = [];
        foreach ($disabilityCategories as $d) {
            $code = strtoupper((string)($d['code'] ?? ''));
            if ($code !== '') {
                $byCode[$code] = $d;
            }
        }

        $grouped = [];
        $placed = [];
        foreach ($groupOrder as $label => $codes) {
            $items = [];
            foreach ($codes as $c) {
                if (isset($byCode[$c])) {
                    $items[] = $byCode[$c];
                    $placed[$c] = true;
                }
            }
            if (!empty($items)) {
                $grouped[$label] = $items;
            }
        }

        // Any extra seeded disabilities not in the explicit map
        foreach ($byCode as $c => $d) {
            if (!isset($placed[$c])) {
                $grouped['Other Specified Disabilities'][] = $d;
            }
        }

        return $grouped;
    }
}

if (!function_exists('pfgGetGroupedAccommodationTypes')) {
    function pfgGetGroupedAccommodationTypes(array $accommodationTypes): array
    {
        $groupMap = [
            'Communication'       => ['ISL_INTERPRETER', 'TACTILE_INTERPRETATION', 'CAPTIONING', 'LIVE_CAPTIONS', 'AUDIO_DESCRIPTION'],
            'Accessible Formats'  => ['BRAILLE_SCHEDULE', 'LARGE_PRINT', 'EASY_READ_MATERIAL', 'SCREEN_READER_DIGITAL'],
            'Mobility & Venue'    => ['WHEELCHAIR_SEATING', 'WHEELCHAIR_LOAN', 'WHEELCHAIR_CHARGING', 'MOBILITY_ASSISTANCE', 'ACCESSIBLE_STATION_TRANSPORT'],
            'Sensory & Support'   => ['SIGHTED_GUIDE', 'SENSORY_SANCTUARY', 'ONSITE_MEDICAL_SUPPORT', 'DIETARY', 'DIETARY_ACCESS_SUPPORT', 'OTHER'],
        ];

        $byCode = [];
        foreach ($accommodationTypes as $a) {
            $code = strtoupper((string)($a['code'] ?? ''));
            if ($code !== '') {
                $byCode[$code] = $a;
            }
        }

        // Ensure key organizer options are always represented if DB seed hasn't added them yet
        $defaults = [
            'ISL_INTERPRETER'              => 'Indian Sign Language (ISL) Interpreter',
            'TACTILE_INTERPRETATION'       => 'Tactile Interpretation (Deafblind support)',
            'CAPTIONING'                   => 'Live Captioning (CART) on screens',
            'LIVE_CAPTIONS'                => 'Live Captions (CART / Speech-to-Text)',
            'AUDIO_DESCRIPTION'            => 'Audio Description for films & stage programmes',
            'BRAILLE_SCHEDULE'             => 'Braille Schedule & Programme',
            'LARGE_PRINT'                  => 'Large-Print Material',
            'EASY_READ_MATERIAL'           => 'Easy-to-Read / Plain-Language Material',
            'SCREEN_READER_DIGITAL'        => 'Screen-Reader-Compatible Digital Material',
            'WHEELCHAIR_SEATING'           => 'Wheelchair-Accessible Seating & Toilets',
            'WHEELCHAIR_LOAN'              => 'Wheelchair to Borrow at the Venue',
            'WHEELCHAIR_CHARGING'          => 'Power Wheelchair Charging Bay',
            'MOBILITY_ASSISTANCE'          => 'Mobility Assistance / On-Campus Shuttle',
            'ACCESSIBLE_STATION_TRANSPORT' => 'Accessible Transport from Railway Station / Bus Stand',
            'SIGHTED_GUIDE'                => 'Sighted Guide / Peer Orientation Support',
            'SENSORY_SANCTUARY'            => 'Quiet Space / Sensory-Friendly Area Access',
            'ONSITE_MEDICAL_SUPPORT'       => 'On-Site Medical Support Awareness',
            'DIETARY'                      => 'Dietary Accommodation',
            'OTHER'                        => 'Other Accessibility Requirement (describe below)',
        ];
        foreach ($defaults as $code => $label) {
            if (!isset($byCode[$code])) {
                // Skip duplicate LIVE_CAPTIONS if CAPTIONING already exists unless explicitly present
                if ($code === 'LIVE_CAPTIONS' && isset($byCode['CAPTIONING'])) {
                    continue;
                }
                $byCode[$code] = ['code' => $code, 'label' => $label, 'description' => null];
            }
        }

        $grouped = [];
        $placed = [];
        foreach ($groupMap as $groupLabel => $codes) {
            $items = [];
            foreach ($codes as $c) {
                if (isset($byCode[$c])) {
                    $items[] = $byCode[$c];
                    $placed[$c] = true;
                }
            }
            if (!empty($items)) {
                $grouped[$groupLabel] = $items;
            }
        }
        foreach ($byCode as $c => $item) {
            if (!isset($placed[$c])) {
                $grouped['Sensory & Support'][] = $item;
            }
        }

        return $grouped;
    }
}

if (!function_exists('pfgRenderSharedAccessibility')) {
    function pfgRenderSharedAccessibility(
        array $disabilityCategories,
        array $accommodationTypes,
        array $initialValues = []
    ): string {
        $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        $selectedPwd = strtoupper(trim((string)($initialValues['isPersonWithDisability'] ?? '')));
        $selectedDisCodes = array_map('strtoupper', is_array($initialValues['disabilityCategoryCodes'] ?? null) ? $initialValues['disabilityCategoryCodes'] : []);
        $selfDesc = (string)($initialValues['disabilitySelfDescription'] ?? '');
        $selectedAccomm = array_map('strtoupper', is_array($initialValues['accommodationTypes'] ?? null) ? $initialValues['accommodationTypes'] : []);
        $accommNotes = (string)($initialValues['accommodationFreeText'] ?? '');
        $escortAccompanying = !empty($initialValues['escortAccompanying']);
        $escortName = (string)($initialValues['escortName'] ?? '');

        $disGroups = pfgGetUmbrellaDisabilityGroups($disabilityCategories);
        $accommGroups = pfgGetGroupedAccommodationTypes($accommodationTypes);

        ob_start();
        ?>
        <section
          id="pfg-shared-accessibility-module"
          data-shared-accessibility="true"
          aria-labelledby="shared-a11y-heading"
          class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7"
        >
          <div class="mb-5">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">
              Accessibility &amp; Support (Optional)
            </p>
            <h2 id="shared-a11y-heading" class="mt-1 text-xl font-bold text-[#202D59]">
              Accessibility &amp; Reasonable Accommodations
            </h2>
          </div>

          <!-- Privacy Assurance Banner -->
          <div
            role="note"
            class="mb-6 rounded-xl border-l-4 border-[#5B2A86] bg-[#F7F4FC] p-4 text-sm text-[#202D59]"
          >
            <p class="font-bold text-[#2B144D]">Your Privacy &amp; Rights Are Protected</p>
            <p class="mt-1 leading-relaxed text-[#383242]">
              Sharing disability information is <strong>100% voluntary</strong> and never affects your eligibility.
              You can request any accessibility accommodation below <strong>without disclosing any disability or diagnosis</strong>.
              All free-text details are encrypted at rest (AES-256-GCM), visible only to authorized accessibility coordinators,
              and never displayed on Registration Desk check-in screens.
            </p>
          </div>

          <!-- 1. Voluntary Disability Self-Identification -->
          <fieldset class="mb-6 border-0 p-0">
            <legend class="text-base font-bold text-[#202D59]">
              Do you identify as a person with a disability? <span class="text-sm font-normal text-[#46537C]">(Optional)</span>
            </legend>
            <p id="pfg-pwd-help" class="mt-1 text-sm text-[#46537C]">
              Choosing &ldquo;No&rdquo;, &ldquo;Prefer not to say&rdquo;, or leaving this blank will still let you request any accommodation below.
            </p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3" role="radiogroup" aria-describedby="pfg-pwd-help">
              <?php
              $pwdOptions = [
                  'YES'               => 'Yes',
                  'NO'                => 'No',
                  'PREFER_NOT_TO_SAY' => 'Prefer not to say',
              ];
              foreach ($pwdOptions as $val => $label):
                  $id = 'pfg-pwd-' . strtolower($val);
                  $checked = ($selectedPwd === $val);
              ?>
                <label
                  for="<?= $esc($id) ?>"
                  class="touch-target flex cursor-pointer items-center gap-3 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-4 py-3 text-sm font-semibold text-[#202D59] transition-colors hover:border-[#A459FF]"
                >
                  <input
                    type="radio"
                    id="<?= $esc($id) ?>"
                    name="isPersonWithDisability"
                    value="<?= $esc($val) ?>"
                    <?= $checked ? 'checked' : '' ?>
                    class="h-5 w-5 shrink-0 accent-[#5B2A86]"
                  />
                  <span><?= $esc($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- 2. Conditional 21 RPwD Act 2016 Disability Categories (visible ONLY when YES) -->
          <div
            id="pfg-disability-disclosure-panel"
            <?= $selectedPwd === 'YES' ? '' : 'hidden' ?>
            class="mb-7 rounded-xl border-2 border-[#DDDDF9] bg-[#FAF9FF] p-4 sm:p-5"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="text-base font-bold text-[#202D59]">
                  Specified Disabilities under the RPwD Act, 2016 <span class="text-sm font-normal text-[#46537C]">(Optional)</span>
                </h3>
                <p class="mt-1 text-sm text-[#46537C]">
                  Select any that apply, or search below by keyword.
                </p>
              </div>
              <span
                id="pfg-a11y-search-status"
                role="status"
                aria-live="polite"
                class="rounded-full bg-[#DDDDF9] px-3 py-1 text-xs font-bold text-[#202D59]"
              >
                Showing all <?= count($disabilityCategories) ?> categories
              </span>
            </div>

            <div class="mt-3">
              <label for="pfg-a11y-disability-search" class="sr-only">Filter disability categories</label>
              <input
                type="search"
                id="pfg-a11y-disability-search"
                placeholder="Search disabilities (e.g., Blindness, Hearing, Autism, Locomotor)..."
                autocomplete="off"
                class="touch-target w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-4 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              />
            </div>

            <div id="pfg-a11y-disability-groups" class="mt-4 space-y-4">
              <?php foreach ($disGroups as $groupName => $items): ?>
                <fieldset
                  data-disability-group="<?= $esc($groupName) ?>"
                  class="rounded-xl border border-[#DCE5F7] bg-white p-3.5"
                >
                  <legend class="px-1 text-sm font-bold text-[#5B2A86]"><?= $esc($groupName) ?></legend>
                  <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <?php foreach ($items as $item):
                        $code = strtoupper((string)$item['code']);
                        $name = (string)$item['name'];
                        $desc = (string)($item['description'] ?? '');
                        $cid = 'pfg-dis-' . strtolower($code);
                        $isChecked = in_array($code, $selectedDisCodes, true);
                        $searchBlob = strtolower($groupName . ' ' . $name . ' ' . $code . ' ' . $desc);
                    ?>
                      <label
                        for="<?= $esc($cid) ?>"
                        data-disability-item="true"
                        data-search-text="<?= $esc($searchBlob) ?>"
                        class="touch-target flex cursor-pointer items-start gap-3 rounded-lg border border-transparent px-3 py-2.5 text-sm text-[#202D59] hover:border-[#DDDDF9] hover:bg-[#F7F4FC]"
                      >
                        <input
                          type="checkbox"
                          id="<?= $esc($cid) ?>"
                          name="disabilityCategoryCodes[]"
                          value="<?= $esc($code) ?>"
                          <?= $isChecked ? 'checked' : '' ?>
                          class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
                        />
                        <span class="font-medium leading-snug"><?= $esc($name) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>
              <?php endforeach; ?>
            </div>

            <div class="mt-4">
              <label for="pfg-disability-self-desc" class="block text-sm font-bold text-[#202D59]">
                Self-Describe or Additional Context <span class="font-normal text-[#46537C]">(Optional, encrypted)</span>
              </label>
              <textarea
                id="pfg-disability-self-desc"
                name="disabilitySelfDescription"
                rows="3"
                maxlength="1000"
                placeholder="Share any context in your own preferred terminology (optional)..."
                aria-describedby="pfg-disability-self-desc-count"
                class="mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              ><?= $esc($selfDesc) ?></textarea>
              <p id="pfg-disability-self-desc-count" class="mt-1 text-right text-xs text-[#46537C]">
                <span data-char-count-for="pfg-disability-self-desc"><?= mb_strlen($selfDesc) ?></span> / 1000 characters
              </p>
            </div>
          </div>

          <!-- 3. Always-Visible Accommodations Checklist (No diagnosis required — CAD-07) -->
          <fieldset class="mb-6 border-0 p-0">
            <legend class="text-base font-bold text-[#202D59]">
              Accessibility Accommodations You Would Like at the Festival <span class="text-sm font-normal text-[#46537C]">(Open to everyone, optional)</span>
            </legend>
            <p class="mt-1 text-sm text-[#46537C]">
              Select any communication, format, mobility, or sensory support that will help you participate comfortably.
            </p>

            <div class="mt-4 space-y-4">
              <?php foreach ($accommGroups as $grpTitle => $items): ?>
                <div class="rounded-xl border border-[#DCE5F7] bg-[#F9FAFF] p-4">
                  <h3 class="text-sm font-bold uppercase tracking-wide text-[#5B2A86]"><?= $esc($grpTitle) ?></h3>
                  <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
                    <?php foreach ($items as $acc):
                        $code = strtoupper((string)$acc['code']);
                        $label = (string)$acc['label'];
                        $aid = 'pfg-acc-' . strtolower($code);
                        $isChecked = in_array($code, $selectedAccomm, true);
                    ?>
                      <label
                        for="<?= $esc($aid) ?>"
                        class="touch-target flex cursor-pointer items-start gap-3 rounded-lg border border-transparent bg-white px-3 py-2.5 text-sm text-[#202D59] shadow-2xs hover:border-[#A459FF]"
                      >
                        <input
                          type="checkbox"
                          id="<?= $esc($aid) ?>"
                          name="accommodationTypes[]"
                          value="<?= $esc($code) ?>"
                          <?= $isChecked ? 'checked' : '' ?>
                          class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
                        />
                        <span class="font-medium leading-snug"><?= $esc($label) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- 4. Free-Text Accommodation Notes -->
          <div class="mb-6">
            <label for="pfg-accomm-free-text" class="block text-sm font-bold text-[#202D59]">
              Specific Accessibility Support Details <span class="font-normal text-[#46537C]">(Optional, encrypted)</span>
            </label>
            <p id="pfg-accomm-free-text-help" class="mt-0.5 text-xs text-[#46537C]">
              Describe any specific venue, seating, communication, assistive device, or support details our accessibility coordinators should know.
            </p>
            <textarea
              id="pfg-accomm-free-text"
              name="accommodationFreeText"
              rows="3"
              maxlength="1000"
              aria-describedby="pfg-accomm-free-text-help pfg-accomm-free-text-count"
              placeholder="Example: Front-row aisle seat near ISL interpreter, quiet break room access..."
              class="mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
            ><?= $esc($accommNotes) ?></textarea>
            <p id="pfg-accomm-free-text-count" class="mt-1 text-right text-xs text-[#46537C]">
              <span data-char-count-for="pfg-accomm-free-text"><?= mb_strlen($accommNotes) ?></span> / 1000 characters
            </p>
          </div>

          <!-- 5. Personal Assistant / Escort Accompanying -->
          <div class="rounded-xl border border-[#DCE5F7] bg-[#F9FAFF] p-4">
            <label
              for="pfg-escort-accompanying"
              class="touch-target flex cursor-pointer items-center gap-3 text-sm font-bold text-[#202D59]"
            >
              <input
                type="checkbox"
                id="pfg-escort-accompanying"
                name="escortAccompanying"
                value="1"
                <?= $escortAccompanying ? 'checked' : '' ?>
                class="h-5 w-5 shrink-0 accent-[#5B2A86]"
              />
              <span>A personal assistant or escort will accompany me at the festival</span>
            </label>
            <p class="mt-1 pl-8 text-xs text-[#46537C]">
              For adult support companions or interpreters. (Under-18 legal guardian consent is collected separately in Section 1 for Attendees.)
            </p>

            <div id="pfg-escort-name-wrap" <?= $escortAccompanying ? '' : 'hidden' ?> class="mt-3 pl-8">
              <label for="pfg-escort-name" class="block text-sm font-semibold text-[#202D59]">
                Personal Assistant / Escort Name <span class="font-normal text-[#46537C]">(Optional, encrypted)</span>
              </label>
              <input
                type="text"
                id="pfg-escort-name"
                name="escortName"
                maxlength="150"
                value="<?= $esc($escortName) ?>"
                placeholder="Full name of accompanying assistant or escort"
                class="touch-target mt-1.5 w-full max-w-md rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              />
            </div>
          </div>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}

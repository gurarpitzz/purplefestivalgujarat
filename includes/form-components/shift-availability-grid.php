<?php
declare(strict_types=1);

/**
 * Reusable Form Component 3: Festival Day × Shift Availability Grid
 * Shared across /apply/volunteer and /apply/sli.
 * 21, 22, 23 Jan 2027 × Shift 1 (08:00–14:30 IST) & Shift 2 (14:00–20:30 IST).
 */

if (!function_exists('pfgRenderShiftAvailabilityGrid')) {
    function pfgRenderShiftAvailabilityGrid(
        string $fieldName = 'availabilityShifts',
        array $selectedShifts = [],
        string $categoryContext = 'VOLUNTEER'
    ): string {
        $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $selectedUpper = array_map('strtoupper', $selectedShifts);
        $isSli = strtoupper($categoryContext) === 'SLI';

        $days = [
            ['code' => 'DAY_1', 'label' => 'Day 1 — Thursday, 21 January 2027', 'short' => 'Thu, 21 Jan 2027'],
            ['code' => 'DAY_2', 'label' => 'Day 2 — Friday, 22 January 2027',   'short' => 'Fri, 22 Jan 2027'],
            ['code' => 'DAY_3', 'label' => 'Day 3 — Saturday, 23 January 2027', 'short' => 'Sat, 23 Jan 2027'],
        ];

        $note = $isSli
            ? 'Sessions use paired interpreters rotating every 20–30 minutes across two daily shifts. Selecting your available shifts helps SLI coordinators plan sustainable rosters and automatically records your attending festival days.'
            : 'Approved volunteers will be invited to a sensitisation workshop and venue walk-through before the festival; schedule details will be shared by email. Your attending days are automatically derived from your selected shifts.';

        ob_start();
        ?>
        <fieldset class="min-w-0 max-w-full border-0 p-0" aria-describedby="pfg-shift-grid-help">
          <legend class="text-base font-bold text-[#202D59]">
            Festival Day &amp; Shift Availability (21–23 January 2027) <span class="text-red-700" aria-hidden="true">*</span>
          </legend>
          <p id="pfg-shift-grid-help" class="mt-1 text-sm text-[#46537C]">
            Select at least one shift slot when you are available to serve. All checkboxes start unselected.
          </p>

          <div class="relative mt-4 overflow-x-auto rounded-xl border-2 border-[#DCE5F7]" style="position:relative;overflow-x:auto;max-width:100%;">
            <table class="w-full border-collapse text-left text-sm">
              <thead>
                <tr class="bg-[#F2E9FE] text-[#202D59]">
                  <th scope="col" class="px-4 py-3 font-bold">Festival Day</th>
                  <th scope="col" class="px-4 py-3 text-center font-bold">
                    Shift 1 — Morning<br />
                    <span class="text-xs font-medium text-[#46537C]">08:00 – 14:30 IST</span>
                  </th>
                  <th scope="col" class="px-4 py-3 text-center font-bold">
                    Shift 2 — Afternoon &amp; Evening<br />
                    <span class="text-xs font-medium text-[#46537C]">14:00 – 20:30 IST</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#DCE5F7] bg-white">
                <?php foreach ($days as $day):
                    $slot1 = $day['code'] . '_SHIFT_1';
                    $slot2 = $day['code'] . '_SHIFT_2';
                    $chk1 = in_array($slot1, $selectedUpper, true);
                    $chk2 = in_array($slot2, $selectedUpper, true);
                ?>
                  <tr>
                    <th scope="row" class="px-4 py-3.5 font-semibold text-[#202D59]">
                      <span class="block"><?= $esc($day['label']) ?></span>
                    </th>
                    <td class="px-4 py-2 text-center">
                      <label
                        for="pfg-shift-<?= $esc(strtolower($slot1)) ?>"
                        class="touch-target relative inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-[#DCE5F7] bg-[#F9FAFF] px-4 py-2.5 hover:border-[#A459FF]"
                      >
                        <input
                          type="checkbox"
                          id="pfg-shift-<?= $esc(strtolower($slot1)) ?>"
                          name="<?= $esc($fieldName) ?>[]"
                          value="<?= $esc($slot1) ?>"
                          <?= $chk1 ? 'checked' : '' ?>
                          class="h-5 w-5 accent-[#5B2A86]"
                        />
                        <span class="sr-only"><?= $esc($day['label'] . ' — Shift 1 Morning 08:00 to 14:30 IST') ?></span>
                        <span aria-hidden="true" class="text-xs font-semibold text-[#202D59]">Available</span>
                      </label>
                    </td>
                    <td class="px-4 py-2 text-center">
                      <label
                        for="pfg-shift-<?= $esc(strtolower($slot2)) ?>"
                        class="touch-target relative inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-[#DCE5F7] bg-[#F9FAFF] px-4 py-2.5 hover:border-[#A459FF]"
                      >
                        <input
                          type="checkbox"
                          id="pfg-shift-<?= $esc(strtolower($slot2)) ?>"
                          name="<?= $esc($fieldName) ?>[]"
                          value="<?= $esc($slot2) ?>"
                          <?= $chk2 ? 'checked' : '' ?>
                          class="h-5 w-5 accent-[#5B2A86]"
                        />
                        <span class="sr-only"><?= $esc($day['label'] . ' — Shift 2 Afternoon and Evening 14:00 to 20:30 IST') ?></span>
                        <span aria-hidden="true" class="text-xs font-semibold text-[#202D59]">Available</span>
                      </label>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <p class="mt-3 rounded-lg bg-[#F7F4FC] px-4 py-3 text-xs leading-relaxed text-[#46537C]">
            <?= $esc($note) ?>
          </p>
        </fieldset>
        <?php
        return (string)ob_get_clean();
    }
}

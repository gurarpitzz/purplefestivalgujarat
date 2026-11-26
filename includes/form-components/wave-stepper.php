<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Reusable 3-Step Flowing Wave-Node Progress Bar
 * Used on /apply/attendee, /apply/sli, and /apply/volunteer.
 */
if (!function_exists('pfgRenderWaveStepper')) {
    /**
     * @param array<int, array{number:int, title:string, shortTitle?:string}> $steps
     */
    function pfgRenderWaveStepper(array $steps): string
    {
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $total = count($steps);
        if ($total < 1) {
            return '';
        }

        $firstTitle = (string)($steps[0]['title'] ?? 'Step 1');

        ob_start();
        ?>
<nav
  id="pfg-wave-stepper"
  aria-label="Registration form progress"
  class="pfg-wave-stepper"
  data-wave-stepper="true"
  data-total-steps="<?= $total ?>"
>
  <div class="pfg-wave-stepper__top">
    <p id="pfg-wave-step-counter" class="pfg-wave-stepper__counter" aria-live="polite">
      Step 1 of <?= $total ?>
    </p>
    <p id="pfg-wave-step-title" class="pfg-wave-stepper__current-name">
      <?= $esc($firstTitle) ?>
    </p>
  </div>

  <div class="pfg-wave-stepper__stage">
    <!-- Flowing sinusoidal wave connecting Node 1 -> Node 2 -> Node 3 -->
    <svg
      class="pfg-wave-stepper__svg"
      viewBox="0 0 600 52"
      preserveAspectRatio="none"
      aria-hidden="true"
    >
      <!-- Base wave track -->
      <path
        class="pfg-wave-stepper__track"
        d="M 24 26 C 92 6, 148 6, 198 26 C 234 40, 266 40, 300 26 C 334 12, 366 12, 402 26 C 452 46, 508 46, 576 26"
      />
      <!-- Animated progress wave fill (stroke-dashoffset driven by category-forms.js) -->
      <path
        id="pfg-wave-progress-path"
        class="pfg-wave-stepper__fill"
        d="M 24 26 C 92 6, 148 6, 198 26 C 234 40, 266 40, 300 26 C 334 12, 366 12, 402 26 C 452 46, 508 46, 576 26"
      />
    </svg>

    <ol class="pfg-wave-stepper__nodes">
      <?php foreach ($steps as $idx => $st):
          $num = (int)($st['number'] ?? ($idx + 1));
          $title = (string)($st['title'] ?? ('Step ' . $num));
          $isActive = ($num === 1);
      ?>
        <li
          class="pfg-wave-stepper__item<?= $isActive ? ' is-active' : '' ?>"
          data-wave-node-item="<?= $num ?>"
          data-step-title="<?= $esc($title) ?>"
        >
          <button
            type="button"
            class="pfg-wave-stepper__node"
            data-wave-step-btn="<?= $num ?>"
            aria-current="<?= $isActive ? 'step' : 'false' ?>"
            aria-label="Step <?= $num ?> of <?= $total ?>: <?= $esc($title) ?>"
          >
            <span class="pfg-wave-stepper__node-num"><?= $num ?></span>
            <svg
              class="pfg-wave-stepper__node-check"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M20 6 9 17l-5-5" />
            </svg>
          </button>
          <span class="pfg-wave-stepper__meta">
            <span class="pfg-wave-stepper__step-num">Step <?= $num ?></span>
            <span class="pfg-wave-stepper__label"><?= $esc($title) ?></span>
          </span>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</nav>
        <?php
        return (string)ob_get_clean();
    }
}


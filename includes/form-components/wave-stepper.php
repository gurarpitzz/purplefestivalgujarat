<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Branded Registration Shell & 4-Step Progress Bar
 * Shared across /apply/attendee, /apply/sli, and /apply/volunteer.
 */

if (!function_exists('pfgRenderRegEdgeRibbons')) {
    function pfgRenderRegEdgeRibbons(): string
    {
        return <<<'SVG'
<svg class="pfg-reg-ribbon pfg-reg-ribbon--left" viewBox="0 0 180 760" preserveAspectRatio="none" fill="none" aria-hidden="true">
  <defs>
    <linearGradient id="pfgRibbonLeft" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#B983FF" stop-opacity="0.45" />
      <stop offset="50%" stop-color="#8B3DFF" stop-opacity="0.28" />
      <stop offset="100%" stop-color="#D4B5FF" stop-opacity="0.1" />
    </linearGradient>
  </defs>
  <path d="M-24,70 C54,155 102,250 70,355 C42,445 -18,480 -28,530 L-28,455 C12,410 42,355 42,290 C42,225 14,160 -24,118 Z" fill="url(#pfgRibbonLeft)" />
  <path d="M-20,380 C44,440 76,540 24,660 L-20,695 C20,585 26,490 -20,428 Z" fill="#C49BFF" fill-opacity="0.24" />
</svg>
<svg class="pfg-reg-ribbon pfg-reg-ribbon--right" viewBox="0 0 180 760" preserveAspectRatio="none" fill="none" aria-hidden="true">
  <defs>
    <linearGradient id="pfgRibbonRight" x1="1" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#B983FF" stop-opacity="0.4" />
      <stop offset="55%" stop-color="#7C2AE8" stop-opacity="0.26" />
      <stop offset="100%" stop-color="#D4B5FF" stop-opacity="0.12" />
    </linearGradient>
  </defs>
  <path d="M204,190 C134,265 92,355 112,455 C128,538 176,580 204,615 L204,545 C166,508 136,462 138,402 C140,342 166,282 204,235 Z" fill="url(#pfgRibbonRight)" />
  <path d="M200,455 C144,515 110,605 146,725 L200,740 C160,630 162,550 200,498 Z" fill="#C49BFF" fill-opacity="0.22" />
</svg>
SVG;
    }
}

if (!function_exists('pfgRenderRegCardHeader')) {
    function pfgRenderRegCardHeader(string $categoryTitle): string
    {
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $emblem = function_exists('renderPurpleFestEmblemSvg')
            ? renderPurpleFestEmblemSvg('pfg-reg-banner__emblem')
            : '';

        ob_start();
        ?>
<header class="pfg-reg-banner" data-surface="dark">
  <svg class="pfg-reg-banner__waves" viewBox="0 0 740 120" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0,48 C155,8 310,92 490,44 C595,16 675,26 740,42 L740,120 L0,120 Z" fill="#9D4EDD" fill-opacity="0.28" />
    <path d="M0,74 C190,114 395,22 575,68 C655,88 702,76 740,62 L740,120 L0,120 Z" fill="#C77DFF" fill-opacity="0.18" />
  </svg>
  <div class="pfg-reg-banner__inner">
    <span class="pfg-reg-banner__brand" aria-hidden="true">
      <?= $emblem ?>
      <span>PurpleFest</span>
    </span>
    <span class="pfg-reg-banner__divider" aria-hidden="true"></span>
    <h1 class="pfg-reg-banner__title"><?= $esc($categoryTitle) ?></h1>
  </div>
</header>
        <?php
        return (string)ob_get_clean();
    }
}

if (!function_exists('pfgRenderWaveStepper')) {
    /**
     * @param array<int, array{number:int, title:string}> $steps
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
  aria-label="Registration progress"
  class="pfg-wave-stepper"
  data-wave-stepper="true"
  data-total-steps="<?= $total ?>"
>
  <p id="pfg-wave-step-counter" class="sr-only" aria-live="polite">
    Step 1 of <?= $total ?>: <?= $esc($firstTitle) ?>
  </p>

  <div class="pfg-wave-stepper__stage">
    <!-- Subtle flowing purple wave connector running through the 4 nodes -->
    <svg
      class="pfg-wave-stepper__svg"
      viewBox="0 0 600 36"
      preserveAspectRatio="none"
      aria-hidden="true"
    >
      <path
        class="pfg-wave-stepper__track"
        d="M 10 18 C 60 11, 140 25, 200 18 C 260 11, 340 25, 400 18 C 460 11, 540 25, 590 18"
      />
      <path
        id="pfg-wave-progress-path"
        class="pfg-wave-stepper__fill"
        d="M 10 18 C 60 11, 140 25, 200 18 C 260 11, 340 25, 400 18 C 460 11, 540 25, 590 18"
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
            <span class="pfg-wave-stepper__circle">
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
            </span>
          </button>
          <span class="pfg-wave-stepper__label"><?= $esc($title) ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</nav>
        <?php
        return (string)ob_get_clean();
    }
}

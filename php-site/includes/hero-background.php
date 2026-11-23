<?php
declare(strict_types=1);

$heroStrokeColor = isset($strokeColor) && is_string($strokeColor) ? $strokeColor : '#9783C5';
$heroSecondaryStrokeColor = isset($secondaryStrokeColor) && is_string($secondaryStrokeColor) ? $secondaryStrokeColor : '#B5A4DF';
?>
<div
  aria-hidden="true"
  class="pointer-events-none select-none absolute inset-0 z-0 overflow-hidden"
>
  <!-- Subtle luminous radial center wash keeping headline & body copy spotless -->
  <div
    class="absolute inset-0"
    style="background: radial-gradient(ellipse 56% 52% at 50% 42%, rgba(246, 239, 255, 0.78) 0%, rgba(242, 233, 254, 0.35) 64%, rgba(235, 224, 252, 0.55) 100%);"
  ></div>

  <!-- CENTER-BOTTOM CONNECTING HORIZON WAVES -->
  <svg
    viewBox="0 0 1360 272"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    class="absolute bottom-0 left-[14%] h-auto w-[72%] opacity-85 md:opacity-95"
    aria-hidden="true"
    focusable="false"
  >
    <image
      href="/assets/illustrations/horizon-waves.png"
      x="0"
      y="0"
      width="1360"
      height="272"
      preserveAspectRatio="none"
    />
    <path
      d="M 40 218 Q 340 162 660 212 T 1320 174"
      stroke="<?= htmlspecialchars($heroSecondaryStrokeColor, ENT_QUOTES, 'UTF-8') ?>"
      stroke-opacity="0.32"
      stroke-width="1.2"
      stroke-linecap="round"
    />
  </svg>

  <!-- UPPER LEFT — Statue of Unity -->
  <div class="absolute left-0 top-[1%] w-[155px] opacity-75 sm:top-[2%] sm:w-[225px] sm:opacity-90 md:top-[4%] md:w-[295px] md:opacity-95 lg:w-[375px] lg:opacity-100 xl:top-[5%] xl:w-[445px]">
    <svg
      viewBox="0 0 700 440"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="h-auto w-full"
      aria-hidden="true"
      focusable="false"
    >
      <image
        href="/assets/illustrations/statue-of-unity.png"
        x="0"
        y="0"
        width="700"
        height="440"
        preserveAspectRatio="xMinYMid meet"
      />
      <path
        d="M 0 382 Q 210 356 450 388 T 690 416"
        stroke="<?= htmlspecialchars($heroSecondaryStrokeColor, ENT_QUOTES, 'UTF-8') ?>"
        stroke-opacity="0.35"
        stroke-width="1.1"
        stroke-linecap="round"
      />
      <path
        d="M 118 332 L 264 312 L 442 354"
        stroke="<?= htmlspecialchars($heroStrokeColor, ENT_QUOTES, 'UTF-8') ?>"
        stroke-opacity="0.22"
        stroke-width="0.9"
        stroke-linecap="round"
      />
    </svg>
  </div>

  <!-- LOWER LEFT — Adalaj Stepwell -->
  <div class="hidden md:block md:absolute md:bottom-0 md:left-0 md:w-[310px] md:opacity-95 lg:w-[400px] lg:opacity-100 xl:w-[475px]">
    <svg
      viewBox="0 0 720 572"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="h-auto w-full"
      aria-hidden="true"
      focusable="false"
    >
      <image
        href="/assets/illustrations/adalaj-stepwell.png"
        x="0"
        y="0"
        width="720"
        height="572"
        preserveAspectRatio="xMinYMax meet"
      />
      <path
        d="M 320 404 Q 495 362 695 438"
        stroke="<?= htmlspecialchars($heroSecondaryStrokeColor, ENT_QUOTES, 'UTF-8') ?>"
        stroke-opacity="0.32"
        stroke-width="1.1"
        stroke-linecap="round"
      />
      <path
        d="M 0 448 Q 210 478 460 546"
        stroke="<?= htmlspecialchars($heroStrokeColor, ENT_QUOTES, 'UTF-8') ?>"
        stroke-opacity="0.24"
        stroke-width="0.95"
        stroke-linecap="round"
      />
    </svg>
  </div>

  <!-- UPPER RIGHT — Somnath Temple -->
  <div class="absolute right-0 top-[2%] w-[165px] opacity-75 sm:top-[4%] sm:w-[235px] sm:opacity-90 md:top-[7%] md:w-[300px] md:opacity-95 lg:w-[385px] lg:opacity-100 xl:top-[9%] xl:w-[455px]">
    <svg
      viewBox="0 0 728 430"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="h-auto w-full"
      aria-hidden="true"
      focusable="false"
    >
      <image
        href="/assets/illustrations/somnath-temple.png"
        x="0"
        y="0"
        width="728"
        height="430"
        preserveAspectRatio="xMaxYMid meet"
      />
      <path
        d="M 24 388 Q 260 334 510 316 T 728 324"
        stroke="<?= htmlspecialchars($heroSecondaryStrokeColor, ENT_QUOTES, 'UTF-8') ?>"
        stroke-opacity="0.32"
        stroke-width="1.1"
        stroke-linecap="round"
      />
      <path
        d="M 160 398 Q 410 344 728 352"
        stroke="<?= htmlspecialchars($heroStrokeColor, ENT_QUOTES, 'UTF-8') ?>"
        stroke-opacity="0.22"
        stroke-width="0.95"
        stroke-linecap="round"
      />
    </svg>
  </div>

  <!-- LOWER RIGHT — Gujarati Lakeside Pavilion -->
  <div class="hidden md:block md:absolute md:bottom-0 md:right-0 md:w-[310px] md:opacity-95 lg:w-[400px] lg:opacity-100 xl:w-[480px]">
    <svg
      viewBox="0 0 728 502"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="h-auto w-full"
      aria-hidden="true"
      focusable="false"
    >
      <image
        href="/assets/illustrations/gujarat-pavilion.png"
        x="0"
        y="0"
        width="728"
        height="502"
        preserveAspectRatio="xMaxYMax meet"
      />
      <path
        d="M 36 244 Q 260 226 520 228 T 728 232"
        stroke="<?= htmlspecialchars($heroSecondaryStrokeColor, ENT_QUOTES, 'UTF-8') ?>"
        stroke-opacity="0.30"
        stroke-width="1.05"
        stroke-linecap="round"
      />
      <path
        d="M 110 472 Q 360 398 640 422"
        stroke="<?= htmlspecialchars($heroStrokeColor, ENT_QUOTES, 'UTF-8') ?>"
        stroke-opacity="0.24"
        stroke-width="0.95"
        stroke-linecap="round"
      />
    </svg>
  </div>
</div>

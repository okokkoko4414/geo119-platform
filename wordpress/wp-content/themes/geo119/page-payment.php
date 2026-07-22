<?php
/**
 * Template: Payment page with cost estimate before confirmation.
 * Template Name: Payment
 */

declare(strict_types=1);

get_header();
?>

<main id="main" class="section" role="main">
  <div class="page-container">
    <div class="mx-auto max-w-3xl">
      <h1 class="text-3xl font-bold text-surface-900"><?php _e('Payment', 'geo119'); ?></h1>
      <p class="mt-2 text-surface-500"><?php _e('Review your order and confirm payment.', 'geo119'); ?></p>

      <!-- Cost Estimate Card -->
      <div class="mt-8 card-base overflow-hidden">
        <div class="bg-surface-50 px-6 py-4 border-b border-surface-200">
          <h2 class="text-lg font-semibold text-surface-900"><?php _e('Compute Cost Estimate', 'geo119'); ?></h2>
          <p class="text-sm text-surface-500"><?php _e('Estimated cost before payment confirmation.', 'geo119'); ?></p>
        </div>

        <div class="p-6">
          <div class="flex items-center justify-between py-3">
            <span class="text-surface-600"><?php _e('Service', 'geo119'); ?></span>
            <span class="font-medium text-surface-900"><?php _e('Translation Job', 'geo119'); ?></span>
          </div>
          <div class="flex items-center justify-between py-3">
            <span class="text-surface-600"><?php _e('Description', 'geo119'); ?></span>
            <span class="text-sm text-surface-500 text-right max-w-xs"><?php _e('Content optimization and translation', 'geo119'); ?></span>
          </div>

          <hr class="my-3 border-surface-200">

          <div class="flex items-center justify-between py-3">
            <span class="text-surface-600"><?php _e('Subtotal', 'geo119'); ?></span>
            <span class="font-mono font-medium text-surface-900">1,500 <?php _e('points', 'geo119'); ?></span>
          </div>
          <div class="flex items-center justify-between py-3">
            <span class="text-surface-600"><?php _e('Compute cost', 'geo119'); ?></span>
            <span class="font-mono font-medium text-surface-900">150 <?php _e('points', 'geo119'); ?></span>
          </div>

          <hr class="my-3 border-surface-200">

          <!-- Cost in local currency -->
          <div class="rounded-lg bg-accent-50 border border-accent-200 p-4 my-4">
            <div class="flex items-center justify-between">
              <div>
                <span class="text-sm font-medium text-accent-800"><?php _e('Estimated Cost', 'geo119'); ?></span>
                <p class="text-xs text-accent-600 mt-0.5"><?php _e('Based on current compute rates.', 'geo119'); ?></p>
              </div>
              <div class="text-right">
                <div class="text-2xl font-bold text-accent-800 font-mono">1,650 <?php _e('points', 'geo119'); ?></div>
                <div class="text-sm text-accent-600 font-mono">≈ 41,250 <?php _e('VND', 'geo119'); ?></div>
              </div>
            </div>
          </div>

          <hr class="my-3 border-surface-200">

          <div class="flex items-center justify-between py-3">
            <span class="text-lg font-semibold text-surface-900"><?php _e('Total', 'geo119'); ?></span>
            <span class="text-lg font-bold text-surface-900 font-mono">1,650 <?php _e('points', 'geo119'); ?></span>
          </div>
        </div>
      </div>

      <!-- Payment Method -->
      <div class="mt-8 card-base p-6">
        <h2 class="text-lg font-semibold text-surface-900"><?php _e('Payment Method', 'geo119'); ?></h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="flex items-center gap-3 rounded-lg border border-surface-300 p-4 cursor-pointer hover:border-primary-400 transition-colors has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
            <input type="radio" name="payment_method" value="card" class="h-4 w-4 text-primary-600 focus-ring" checked>
            <div>
              <span class="font-medium text-surface-900"><?php _e('Card', 'geo119'); ?></span>
              <p class="text-xs text-surface-500"><?php _e('Visa, Mastercard, JCB', 'geo119'); ?></p>
            </div>
          </label>

          <label class="flex items-center gap-3 rounded-lg border border-surface-300 p-4 cursor-pointer hover:border-primary-400 transition-colors has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
            <input type="radio" name="payment_method" value="paypal" class="h-4 w-4 text-primary-600 focus-ring">
            <div>
              <span class="font-medium text-surface-900"><?php _e('PayPal', 'geo119'); ?></span>
              <p class="text-xs text-surface-500"><?php _e('Pay with your PayPal account', 'geo119'); ?></p>
            </div>
          </label>
        </div>
      </div>

      <!-- Confirm -->
      <div class="mt-8">
        <button type="submit" class="w-full rounded-lg bg-primary-600 px-8 py-4 text-base font-semibold text-white hover:bg-primary-700 active:bg-primary-800 focus-ring transition-colors shadow-sm">
          <?php _e('Confirm Payment', 'geo119'); ?> — 1,650 <?php _e('points', 'geo119'); ?>
        </button>
        <p class="mt-3 text-center text-xs text-surface-400">
          <?php _e('By confirming, you agree to the GEO119 Terms of Service and Privacy Policy.', 'geo119'); ?>
        </p>
      </div>
    </div>
  </div>
</main>

<?php
get_footer();

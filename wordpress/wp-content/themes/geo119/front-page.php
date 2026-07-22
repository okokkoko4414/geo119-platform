<?php
/**
 * Front page template — real theme, not wireframes.
 */

declare(strict_types=1);

get_header();
?>

<main id="main" role="main">
  <!-- Hero -->
  <section class="relative overflow-hidden bg-gradient-to-br from-primary-900 via-primary-800 to-primary-950 text-white">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary-600/20 via-transparent to-transparent"></div>
    <div class="page-container relative py-24 sm:py-32 lg:py-40">
      <div class="max-w-3xl">
        <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl text-balance">
          <?php _e('Content Optimization Platform', 'geo119'); ?>
        </h1>
        <p class="mt-6 text-lg sm:text-xl text-primary-100 leading-relaxed max-w-2xl">
          <?php _e('AI-powered content optimization and translation for global audiences. Reach more users in their native language.', 'geo119'); ?>
        </p>
        <div class="mt-10 flex flex-col sm:flex-row gap-4">
          <a href="<?php echo esc_url(home_url('/get-started')); ?>" class="btn-primary inline-flex rounded-lg px-8 py-4 text-base font-semibold shadow-lg hover:shadow-xl transition-shadow">
            <?php _e('Get Started Free', 'geo119'); ?>
          </a>
          <a href="<?php echo esc_url(home_url('/about')); ?>" class="inline-flex items-center justify-center rounded-lg border border-primary-400 px-8 py-4 text-base font-semibold text-white hover:bg-white/10 transition-colors">
            <?php _e('Learn More', 'geo119'); ?>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section class="section bg-white">
    <div class="page-container">
      <div class="text-center max-w-2xl mx-auto">
        <h2 class="text-3xl font-bold text-surface-900 sm:text-4xl">
          <?php _e('Why GEO119?', 'geo119'); ?>
        </h2>
        <p class="mt-4 text-lg text-surface-500">
          <?php _e('Everything you need to take your content global.', 'geo119'); ?>
        </p>
      </div>

      <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Feature 1 -->
        <div class="card-base p-8 hover:shadow-md transition-shadow">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-100 text-primary-700">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
            </svg>
          </div>
          <h3 class="mt-5 text-lg font-semibold text-surface-900">
            <?php _e('Multi-Language Translation', 'geo119'); ?>
          </h3>
          <p class="mt-3 text-surface-500 leading-relaxed">
            <?php _e('Translate your content into 70+ languages with AI-powered accuracy. Automatic locale detection and language switching.', 'geo119'); ?>
          </p>
        </div>

        <!-- Feature 2 -->
        <div class="card-base p-8 hover:shadow-md transition-shadow">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-accent-100 text-accent-700">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="mt-5 text-lg font-semibold text-surface-900">
            <?php _e('Cost Transparency', 'geo119'); ?>
          </h3>
          <p class="mt-3 text-surface-500 leading-relaxed">
            <?php _e('See estimated compute costs in points and your local currency before every operation. No surprise charges.', 'geo119'); ?>
          </p>
        </div>

        <!-- Feature 3 -->
        <div class="card-base p-8 hover:shadow-md transition-shadow sm:col-span-2 lg:col-span-1">
          <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-100 text-primary-700">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <h3 class="mt-5 text-lg font-semibold text-surface-900">
            <?php _e('SEO Built-In', 'geo119'); ?>
          </h3>
          <p class="mt-3 text-surface-500 leading-relaxed">
            <?php _e('JSON-LD structured data, hreflang tags, XML sitemaps, and clean URLs — everything search engines need.', 'geo119'); ?>
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Stats -->
  <section class="bg-surface-50 py-16">
    <div class="page-container">
      <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
        <div class="text-center">
          <div class="text-3xl font-extrabold text-primary-700">70+</div>
          <div class="mt-2 text-sm text-surface-500"><?php _e('Languages', 'geo119'); ?></div>
        </div>
        <div class="text-center">
          <div class="text-3xl font-extrabold text-primary-700">99.9%</div>
          <div class="mt-2 text-sm text-surface-500"><?php _e('Uptime', 'geo119'); ?></div>
        </div>
        <div class="text-center">
          <div class="text-3xl font-extrabold text-primary-700">~500ms</div>
          <div class="mt-2 text-sm text-surface-500"><?php _e('Response Time', 'geo119'); ?></div>
        </div>
        <div class="text-center">
          <div class="text-3xl font-extrabold text-primary-700">24/7</div>
          <div class="mt-2 text-sm text-surface-500"><?php _e('Support', 'geo119'); ?></div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="section bg-primary-700 text-white">
    <div class="page-container text-center max-w-2xl">
      <h2 class="text-3xl font-bold sm:text-4xl">
        <?php _e('Ready to reach a global audience?', 'geo119'); ?>
      </h2>
      <p class="mt-4 text-lg text-primary-100">
        <?php _e('Start optimizing your content today with GEO119\'s AI-powered platform. Free to get started.', 'geo119'); ?>
      </p>
      <a href="<?php echo esc_url(home_url('/get-started')); ?>" class="mt-8 inline-flex rounded-lg bg-white px-8 py-4 text-base font-semibold text-primary-700 hover:bg-primary-50 transition-colors shadow-lg">
        <?php _e('Get Started Free', 'geo119'); ?>
      </a>
    </div>
  </section>
</main>

<?php
get_footer();

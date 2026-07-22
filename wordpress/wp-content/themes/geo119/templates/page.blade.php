<?php
/**
 * Template: Page (Blade-compatible)
 */

declare(strict_types=1);

get_header();
?>

<main id="main" class="page-container" role="main">
    <article <?php post_class('content-container py-12'); ?>>
        <h1 class="text-3xl font-bold text-surface-900 mb-6"><?php the_title(); ?></h1>
        <div class="prose max-w-none">
            <?php the_content(); ?>
        </div>
    </article>
</main>

<?php
get_footer();

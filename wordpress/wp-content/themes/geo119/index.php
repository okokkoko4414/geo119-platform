<?php
/**
 * Main theme template.
 */

declare(strict_types=1);

get_header();
?>

<main id="main" class="page-container" role="main">
    <?php
    if (have_posts()) {
        while (have_posts()) {
            the_post();
            ?>
            <article <?php post_class('content-container py-8'); ?>>
                <h1 class="text-3xl font-bold text-surface-900"><?php the_title(); ?></h1>
                <div class="prose max-w-none mt-6">
                    <?php the_content(); ?>
                </div>
            </article>
            <?php
        }
    } else {
        ?>
        <div class="py-16 text-center">
            <h2 class="text-xl text-surface-600"><?php _e('No content found.', 'geo119'); ?></h2>
        </div>
        <?php
    }
?>
</main>

<?php
get_footer();

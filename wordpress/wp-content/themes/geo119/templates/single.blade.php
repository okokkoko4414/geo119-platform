<?php
/**
 * Template: Single Post (Blade-compatible)
 */

declare(strict_types=1);

get_header();
?>

<main id="main" class="page-container" role="main">
    <?php while (have_posts()): the_post(); ?>
        <article <?php post_class('content-container py-12'); ?>>
            <header class="mb-8">
                <time datetime="<?php echo get_the_date('c'); ?>" class="text-sm text-surface-500">
                    <?php echo get_the_date(); ?>
                </time>
                <h1 class="text-3xl font-bold text-surface-900 mt-2"><?php the_title(); ?></h1>
            </header>

            <div class="prose max-w-none">
                <?php the_content(); ?>
            </div>

            <footer class="mt-12 pt-6 border-t border-surface-200">
                <nav aria-label="Post navigation">
                    <?php
                    previous_post_link('<span class="text-sm text-primary-600 hover:underline">&larr; %link</span>');
                    echo ' ';
                    next_post_link('<span class="text-sm text-primary-600 hover:underline">%link &rarr;</span>');
                    ?>
                </nav>
            </footer>
        </article>
    <?php endwhile; ?>
</main>

<?php
get_footer();

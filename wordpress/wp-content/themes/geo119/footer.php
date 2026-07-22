<?php
/**
 * WordPress footer template — used by all theme pages.
 */

declare(strict_types=1);
?>

<footer class="border-t border-surface-200 bg-white py-8 mt-auto">
    <div class="page-container flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-surface-500">
        <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. <?php _e('All rights reserved.', 'geo119'); ?></p>
        <?php
        wp_nav_menu([
            'theme_location' => 'footer',
            'container_class' => 'flex gap-4',
            'fallback_cb' => false,
        ]);
        ?>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

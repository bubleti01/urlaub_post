<?php
if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main id="primary" class="site-main igw-urlaub-post-single">
    <?php while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
            </header>


            <?php
            $von = igw_urlaub_post_format_date_de((string) get_post_meta(get_the_ID(), 'von_datum', true));
            $bis = igw_urlaub_post_format_date_de((string) get_post_meta(get_the_ID(), 'bis_datum', true));
            $date_line = sprintf(
                esc_html__('vom %1$s bis %2$s', 'igw_wp_urlaub_post'),
                esc_html($von),
                esc_html($bis)
            );
            ?>
            <p class="igw-urlaub-post-single__dates"><?php echo $date_line; ?></p>

            <?php if (has_post_thumbnail()) : ?>
                <div class="post-thumbnail">
                    <?php the_post_thumbnail('large'); ?>
                </div>
            <?php endif; ?>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</main>
<?php
get_footer();

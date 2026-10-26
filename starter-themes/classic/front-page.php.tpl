<?php
/**
 * {{THEME_NAME}} front page.
 *
 * Renders for the home page. When WordPress has a static front page, the
 * hero heading + subheading are editable from that page in wp-admin.
 *
 * Replace the hero markup with something distinctive for the brand.
 * This template is intentionally sparse so the AI can refine it for
 * the user's actual design.
 */
?>
<?php get_header(); ?>
<?php
$uwnext_front_page_id = (int) get_option( 'page_on_front' );
$uwnext_hero_page_id  = ( $uwnext_front_page_id && 'page' === get_post_type( $uwnext_front_page_id ) ) ? $uwnext_front_page_id : 0;
$uwnext_hero_heading  = $uwnext_hero_page_id ? get_post_meta( $uwnext_hero_page_id, 'hero_heading', true ) : '';
$uwnext_hero_subhead  = $uwnext_hero_page_id ? get_post_meta( $uwnext_hero_page_id, 'hero_subheading', true ) : '';
?>

<main class="flex-1">
	<section class="bg-gradient-to-br from-primary-50 to-background py-20">
		<div class="max-w-4xl mx-auto px-6 text-center">
			<h1 class="text-5xl font-bold tracking-tight text-neutral" <?php $uwnext_hero_page_id && function_exists( 'uwnext_edit_attr' ) && uwnext_edit_attr( $uwnext_hero_page_id, 'hero_heading' ); ?>>
				<?php echo esc_html( $uwnext_hero_heading ?: 'Welcome to {{THEME_NAME_PHP}}.' ); ?>
			</h1>
			<p class="mt-4 text-xl text-secondary-600 max-w-2xl mx-auto" <?php $uwnext_hero_page_id && function_exists( 'uwnext_edit_attr' ) && uwnext_edit_attr( $uwnext_hero_page_id, 'hero_subheading' ); ?>>
				<?php echo esc_html( $uwnext_hero_subhead ?: 'A fresh WordPress theme ready for your content.' ); ?>
			</p>
		</div>
	</section>

	<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
		<?php if ( get_the_content() ) : ?>
			<article class="prose max-w-3xl mx-auto px-6 py-16">
				<?php the_content(); ?>
			</article>
		<?php endif; ?>
	<?php endwhile; endif; ?>
</main>

<?php get_footer(); ?>

<?php
/**
 * Articolo del blog.
 *
 * @package Pornovirtuale
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<section class="pv-page-head">
		<div class="container">
			<nav class="pv-breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> / <a href="<?php echo esc_url( pv_page_url( 'blog' ) ); ?>">Blog</a> / <?php echo esc_html( get_the_title() ); ?></nav>
			<h1 class="pv-page-title"><?php the_title(); ?></h1>
			<div class="pv-entry-meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
			</div>
			<?php pv_updated_date(); ?>
		</div>
	</section>

	<section class="section" style="padding-top:16px;">
		<div class="container">
			<?php if ( has_post_thumbnail() ) : ?>
				<div style="max-width:900px;margin-bottom:24px;"><?php the_post_thumbnail( 'pv-hero', array( 'alt' => get_the_title() ) ); ?></div>
			<?php endif; ?>

			<div class="pv-content" style="max-width:820px;">
				<?php the_content(); ?>
			</div>

			<?php if ( get_the_author_meta( 'description' ) ) : ?>
				<div style="max-width:820px;margin-top:32px;padding-top:18px;border-top:1px solid rgba(255,255,255,.12);">
					<p><strong><?php echo esc_html( get_the_author_meta( 'display_name' ) ); ?></strong></p>
					<p style="color:var(--pv-muted);"><?php echo esc_html( get_the_author_meta( 'description' ) ); ?></p>
					<p><a href="<?php echo esc_url( pv_page_url( 'chi-siamo' ) ); ?>">Chi siamo e criteri editoriali</a></p>
				</div>
			<?php endif; ?>

			<?php
			$pv_personaggi = function_exists( 'aria_related_characters_for_post' ) ? aria_related_characters_for_post( get_the_ID(), 4 ) : array();
			if ( $pv_personaggi ) :
				?>
				<div style="max-width:820px;margin-top:32px;">
					<h2 class="pv-section-title pv-section-title--left">Personaggi da conoscere</h2>
					<ul>
						<?php foreach ( $pv_personaggi as $pv_pers ) : ?>
							<li><a href="<?php echo esc_url( $pv_pers['url'] ); ?>">Chat con <?php echo esc_html( $pv_pers['name'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<?php
endwhile;

get_footer();

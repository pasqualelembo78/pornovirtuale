<?php
/**
 * Blog / indice articoli.
 *
 * @package Pornovirtuale
 */

get_header();
?>

<section class="pv-page-head">
	<div class="container">
		<h1 class="pv-page-title">
			<?php
			if ( is_home() ) {
				esc_html_e( 'Guide e novità sul porno virtuale', 'pornovirtuale' );
			} elseif ( is_category() ) {
				single_cat_title();
			} else {
				echo esc_html( wp_strip_all_tags( get_the_archive_title() ) );
			}
			?>
		</h1>
		<p style="color:var(--pv-muted);max-width:760px;"><?php esc_html_e( 'Guide, tutorial, recensioni e novita su porno virtuale e realta virtuale.', 'pornovirtuale' ); ?></p>
		<p style="color:var(--pv-muted);max-width:760px;">
			Il blog raccoglie articoli scritti per chi vuole capire come funzionano le cam VR e il porno immersivo:
			come scegliere una stanza, come usare un visore, quali sono i formati 180 e 360 e come riconoscere i contenuti valevoli.
			Si parte dalle basi e si arriva ai temi più specifici, con un linguaggio semplice e senza giri di parole.
		</p>
	</div>
</section>

<section class="section" style="padding-top:20px;">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="pv-grid pv-grid--articles">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article class="pv-card pv-card--article">
						<div class="pv-card__body">
							<p class="pv-card__meta"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></p>
							<h3 class="pv-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<p class="pv-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24, '...' ) ); ?></p>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div>
			<div class="pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p class="pv-empty"><?php esc_html_e( 'Nessun articolo pubblicato al momento.', 'pornovirtuale' ); ?></p>
		<?php endif; ?>

		<?php if ( is_home() ) : ?>
			<?php
			$pv_categorie_blog = get_categories(
				array(
					'hide_empty' => false,
					'exclude'    => array( 1 ),
					'number'     => 12,
				)
			);
			if ( $pv_categorie_blog ) :
				?>
				<div style="max-width:820px;margin-top:30px;">
					<h2 class="pv-section-title pv-section-title--left"><?php esc_html_e( 'Argomenti del blog', 'pornovirtuale' ); ?></h2>
					<div class="page-links">
						<?php foreach ( $pv_categorie_blog as $pv_categoria_blog ) : ?>
							<a class="internal-links" style="margin:0;" href="<?php echo esc_url( get_category_link( $pv_categoria_blog ) ); ?>"><?php echo esc_html( $pv_categoria_blog->name ); ?></a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php
		$pv_personaggi = ( is_home() && function_exists( 'aria_editorial_characters_spread' ) ) ? aria_editorial_characters_spread( 6 ) : array();
		if ( $pv_personaggi ) :
			?>
			<div style="max-width:820px;margin-top:34px;">
				<h2 class="pv-section-title pv-section-title--left"><?php esc_html_e( 'Personaggi in evidenza', 'pornovirtuale' ); ?></h2>
				<p style="color:var(--pv-muted);">
					<?php esc_html_e( 'Schede editoriali da cui partire: ogni personaggio ha una stanza, un carattere e una chat aperta.', 'pornovirtuale' ); ?>
				</p>
				<ul>
					<?php foreach ( $pv_personaggi as $pv_personaggio ) : ?>
						<li><a href="<?php echo esc_url( $pv_personaggio['url'] ); ?>">Chat con <?php echo esc_html( $pv_personaggio['name'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div style="max-width:820px;margin-top:34px;">
			<h2 class="pv-section-title pv-section-title--left">Cosa trovi nel blog</h2>
			<p style="color:var(--pv-muted);">
				Gli articoli nascono dall'esperienza diretta sulle piattaforme di cam VR: spieghiamo cosa funziona,
				cosa evitare e come ottenere il massimo da visori e browser. Ogni guida è pensata per essere letta in pochi minuti
				e messa in pratica subito, senza acquisti obbligatori.
			</p>
			<p style="color:var(--pv-muted);">
				Le categorie del blog coprono anteprime, tutorial, sicurezza, tendenze e notizie sul settore.
				Se non hai mai provato una stanza VR, parti dalle guide di base: trovi indicazioni su login, privacy, connessione
				e uso del visore. Se invece cerchi solo contenuti, torna alla home e scegli una stanza live o un video.
			</p>
		</div>
	</div>
</section>

<?php
get_footer();

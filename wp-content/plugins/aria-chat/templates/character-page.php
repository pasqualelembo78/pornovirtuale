<?php
/**
 * Pagina di un singolo personaggio: /stanza/<categoria>/<slug>/
 *
 * Struttura pensata per la ricerca: gerarchia H1/H2, testo unico, chat aperta,
 * FAQ e maglia di link interni (compagni di stanza, stanze vicine, archivio).
 */

if (!defined('ABSPATH')) {
    exit;
}

$stanza = get_queried_object();
$slug_personaggio = sanitize_title(get_query_var('personaggio'));
$categoria = $stanza ? get_post_field('post_name', $stanza->ID) : '';
$personaggio = aria_char_by_slug($categoria, $slug_personaggio);

if (!$personaggio) {
    wp_safe_redirect(get_permalink($stanza));
    exit;
}

$nome = $personaggio['name'] ?? '';
$nome_completo = trim((string) ($personaggio['full_name'] ?? '')) ?: $nome;
$eta = (int) ($personaggio['age'] ?? 0);
$mestiere = trim((string) ($personaggio['job'] ?? ''));
$essenza = trim((string) ($personaggio['essence'] ?? ''));
$carattere = trim((string) ($personaggio['personality'] ?? ''));
$comportamento = trim((string) ($personaggio['role'] ?? ''));
$tag = array_slice((array) ($personaggio['tags'] ?? array()), 0, 5);
$seo = aria_room_seo($categoria);
$stadio = aria_grid_category_meta($categoria);
$room_url = get_permalink($stanza);
$base_url = rtrim($room_url, '/') . '/';
$editorial = function_exists('aria_char_editorial') ? aria_char_editorial($categoria, $slug_personaggio) : null;
$editorial_ts = $editorial ? aria_char_editorial_mtime($categoria, $slug_personaggio) : 0;
$immagine_personaggio = function_exists('aria_char_social_image_url') ? aria_char_social_image_url($categoria, $slug_personaggio) : '';

// stanze vicine come link orizzontali
$vicine = array();
foreach (aria_room_seo_data() as $altro_slug => $altro) {
    if ($altro_slug === $categoria) {
        continue;
    }
    $vicine[] = $altro_slug;
}
$vicine = array_slice($vicine, 0, 4);

get_header();
?>

<section class="pv-page-head">
    <div class="container">
        <nav class="pv-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>">Home</a> /
            <a href="<?php echo esc_url(get_post_type_archive_link('stanza')); ?>">Stanze</a> /
            <a href="<?php echo esc_url($room_url); ?>"><?php echo esc_html($stadio['name']); ?></a> /
            <?php echo esc_html($nome_completo); ?>
        </nav>
        <h1 class="pv-page-title">Chat con <?php echo esc_html($nome_completo); ?><?php echo $mestiere ? ', ' . esc_html(strtolower($mestiere)) : ''; ?></h1>
        <div class="pv-entry-meta">
            <?php if ($eta) : ?>
                <span><?php echo esc_html($eta); ?> <?php esc_html_e('anni', 'pornovirtuale'); ?></span> &middot;
            <?php endif; ?>
            <a href="<?php echo esc_url($room_url); ?>">Stanza <?php echo esc_html($stadio['name']); ?></a> &middot;
            <span class="rta-label"><strong>18+</strong></span>
        </div>
        <?php if ($editorial_ts) : ?>
            <p class="pv-updated" style="color:var(--pv-muted);font-size:.9em;">
                <?php esc_html_e('Aggiornato il', 'pornovirtuale'); ?> <?php echo esc_html(date_i18n('j F Y', $editorial_ts)); ?>
            </p>
        <?php else : ?>
            <?php pv_updated_date(); ?>
        <?php endif; ?>
        <?php
        $person_schema = array(
            '@context'     => 'https://schema.org',
            '@type'        => 'ProfilePage',
            'name'         => 'Chat con ' . $nome_completo,
            'url'          => $base_url . $slug_personaggio . '/',
            'dateModified' => $editorial_ts ? gmdate('c', $editorial_ts) : get_post_modified_time('c', false, $stanza->ID),
            'mainEntity'   => array(
                '@type'       => 'Person',
                '@id'         => $base_url . $slug_personaggio . '/#person',
                'name'        => $nome_completo,
                'jobTitle'    => $personaggio['role'] ?? '',
                'gender'      => $personaggio['gender_display'] ?? '',
                'description' => aria_char_trim_desc($editorial['chi'] ?? ($personaggio['description'] ?? ''), 320),
                'url'         => $base_url . $slug_personaggio . '/',
                'knowsLanguage' => 'it',
                'knowsAbout'  => array_map(
                    static function ($t) {
                        return str_replace('-', ' ', (string) $t);
                    },
                    array_slice((array) ($personaggio['tags'] ?? array()), 0, 6)
                ),
            ),
        );
        $person_img = $immagine_personaggio ?: get_the_post_thumbnail_url($stanza->ID, 'pv-hero');
        if ($person_img) {
            $person_schema['mainEntity']['image'] = $person_img;
        }
        echo '<script type="application/ld+json">' . wp_json_encode($person_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
        ?>
    </div>
</section>

<section class="section" style="padding-top:16px;">
    <div class="container">

        <?php if ($immagine_personaggio) : ?>
            <img
                class="aria-char-page__hero"
                src="<?php echo esc_url($immagine_personaggio); ?>"
                alt="Chat con <?php echo esc_attr($nome_completo . ($mestiere ? ', ' . strtolower($mestiere) : '')); ?>, personaggio inventato della stanza <?php echo esc_attr($stadio['name']); ?>"
                width="1200"
                height="630"
                loading="eager"
                fetchpriority="high"
                decoding="async"
                style="display:block;width:100%;max-width:1000px;height:auto;margin:0 auto 24px;border-radius:14px;"
            />
        <?php endif; ?>

        <div class="aria-char-page">
            <div class="aria-char-page__testo">
                <?php
                $testo = $editorial ? null : aria_char_page_testo($personaggio, $stadio);
                ?>
                <?php if ($editorial) : ?>
                    <p class="aria-char-page__lead" style="font-size:1.08em;"><?php echo esc_html($editorial['lead'] ?? ''); ?></p>

                    <h2>Chi è <?php echo esc_html($nome); ?></h2>
                    <p><?php echo esc_html($editorial['chi'] ?? ''); ?></p>

                    <h2>Passioni e tempo libero</h2>
                    <p><?php echo esc_html($editorial['passioni'] ?? ''); ?></p>

                    <h2>Come si comporta in chat</h2>
                    <p><?php echo esc_html($editorial['come'] ?? ''); ?></p>

                    <h2>Cosa vorrebbe fare con te</h2>
                    <p><?php echo esc_html($editorial['invito'] ?? ''); ?></p>

                    <h2>Cosa scrivere per iniziare</h2>
                    <p><?php echo esc_html($editorial['iniziare_intro'] ?? ''); ?></p>
                    <ul>
                        <?php foreach ((array) ($editorial['esempi'] ?? array()) as $frase) : ?>
                            <li><?php echo esc_html($frase); ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <h2>A chi piace</h2>
                    <p><?php echo esc_html($editorial['a_chi'] ?? ''); ?></p>
                <?php else : ?>
                    <h2>Chi è <?php echo esc_html($nome); ?></h2>
                    <p><?php echo esc_html($testo['chi'] ?? ''); ?></p>

                    <h2>La scena</h2>
                    <p><?php echo esc_html($testo['scena'] ?? ''); ?></p>

                    <h2>Come si comporta in chat</h2>
                    <p><?php echo esc_html($testo['come'] ?? ''); ?></p>
                    <?php if (!empty($testo['voce'])) : ?>
                        <blockquote style="border-left:3px solid var(--pv-accent,#c9366b);margin:14px 0;padding:6px 0 6px 16px;color:var(--pv-muted);font-style:italic;">
                            <?php echo esc_html($testo['voce'] ?? ''); ?>
                        </blockquote>
                    <?php endif; ?>

                    <h2>Cosa scrivere per iniziare</h2>
                    <ul>
                        <?php foreach ((array) ($testo['iniziare'] ?? array()) as $frase) : ?>
                            <li><?php echo esc_html($frase); ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <h2>A chi piace</h2>
                    <p><?php echo esc_html($testo['a_chi'] ?? ''); ?></p>
                <?php endif; ?>

                <p>
                    <?php echo $editorial ? 'La chat è già aperta qui sotto: ' : esc_html($testo['nota'] ?? ''); ?>
                    scrivi e risponde in pochi secondi, restando nel personaggio.
                    Hai <strong>quattro messaggi gratis</strong>, poi serve l'accesso.
                </p>

                <?php if ($tag) : ?>
                    <h2>Parole chiave</h2>
                    <ul class="aria-char-page__tag">
                        <?php foreach ($tag as $t) : ?>
                            <li><?php echo esc_html($t); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <p style="color:var(--pv-muted);font-size:.9em;">
                    <?php esc_html_e('Personaggio inventato, non una persona reale.', 'pornovirtuale'); ?>
                    <a href="<?php echo esc_url(pv_page_url('personaggi-inventati')); ?>"><?php esc_html_e('Come funzionano i personaggi', 'pornovirtuale'); ?></a>
                    &middot;
                    <a href="<?php echo esc_url(pv_page_url('chi-siamo')); ?>"><?php esc_html_e('Criteri editoriali', 'pornovirtuale'); ?></a>
                </p>
            </div>
        </div>

        <h2 class="aria-char-page__h2-chat">Scrivi a <?php echo esc_html($nome); ?></h2>
        <?php
        $chat_atts = array(
            'character' => $personaggio['id'],
            'category'  => $categoria,
            'width'     => '100%',
            'height'    => '500px',
        );
        $atts = $chat_atts;
        include ARIA_CHAT_PLUGIN_DIR . 'templates/chat-box.php';
        ?>

        <?php
        $faq_coppie = array();
        if ( $editorial && ! empty( $editorial['faq'] ) ) {
            foreach ( $editorial['faq'] as $coppia ) {
                $faq_coppie[] = array( $coppia['q'] ?? '', $coppia['a'] ?? '' );
            }
        } elseif ( ! empty( $seo['faq'] ) ) {
            $faq_coppie = $seo['faq'];
        }
        ?>
        <?php if (!empty($faq_coppie)) : ?>
            <section class="aria-room-faq" style="padding:0;max-width:none;">
                <h2>Domande su <?php echo esc_html($nome); ?></h2>
                <?php foreach ($faq_coppie as $coppia) : ?>
                    <details>
                        <summary><?php echo esc_html($coppia[0]); ?></summary>
                        <p><?php echo esc_html($coppia[1]); ?></p>
                    </details>
                <?php endforeach; ?>
            </section>
            <?php
            $faq_schema = array(
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => array(),
            );
            foreach ($faq_coppie as $coppia) {
                $faq_schema['mainEntity'][] = array(
                    '@type'          => 'Question',
                    'name'           => $coppia[0],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text'  => $coppia[1],
                    ),
                );
            }
            echo '<script type="application/ld+json">' . wp_json_encode($faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
            ?>
        <?php endif; ?>

        <?php
        $compagni = aria_characters_with_slug($categoria, $personaggio['id'], 12, true);
        if ($compagni) :
            ?>
            <div class="aria-char-page__link">
                <h2>Altri personaggi in <?php echo esc_html($stadio['name']); ?></h2>
                <div class="page-links">
                    <?php foreach ($compagni as $altro) : ?>
                        <a class="internal-links" style="margin:0;" href="<?php echo esc_url($base_url . $altro['slug'] . '/'); ?>"><?php echo esc_html($altro['name']); ?></a>
                    <?php endforeach; ?>
                    <a class="internal-links" style="margin:0;" href="<?php echo esc_url($room_url); ?>">Tutti: <?php echo esc_html($stadio['name']); ?></a>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $guide_query = new WP_Query(
            array(
                'post_type'           => 'post',
                'posts_per_page'      => 3,
                'ignore_sticky_posts' => true,
                's'                   => $tag[0] ?? '',
            )
        );
        if (!$guide_query->have_posts()) {
            $guide_query = new WP_Query(array('post_type' => 'post', 'posts_per_page' => 3));
        }
        if ($guide_query->have_posts()) :
            ?>
            <div class="aria-char-page__link">
                <h2>Guide e approfondimenti</h2>
                <ul>
                    <?php while ($guide_query->have_posts()) : $guide_query->the_post(); ?>
                        <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <?php
            wp_reset_postdata();
        endif;
        ?>

        <?php if ($vicine) : ?>
            <div class="aria-char-page__link">
                <h2>Altre stanze di chat</h2>
                <div class="page-links">
                    <?php foreach ($vicine as $altro_slug) : ?>
                        <?php $meta = aria_grid_category_meta($altro_slug); ?>
                        <a class="internal-links" style="margin:0;" href="<?php echo esc_url(home_url('/stanza/' . $altro_slug . '/')); ?>"><?php echo esc_html($meta['name']); ?></a>
                    <?php endforeach; ?>
                    <a class="internal-links" style="margin:0;" href="<?php echo esc_url(get_post_type_archive_link('stanza')); ?>">Tutte le stanze</a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php get_footer(); ?>

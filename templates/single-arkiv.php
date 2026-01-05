<?php
// Plugin template: Single Arkiv
get_header();

if (have_posts()) : while (have_posts()) : the_post();

  $post_id = get_the_ID();

  // Taxonomy terms (Mappe)
  $terms = get_the_terms($post_id, 'mappe');

  // Galleri IDs (som du gemmer i dit plugin)
  $gallery_ids = get_post_meta($post_id, '_arkiv_gallery_ids', true);
  if (!is_array($gallery_ids)) {
    $gallery_ids = [];
  }
  ?>

  <main class="arkiv-single">
    <div class="arkiv-wrap">

      <header class="arkiv-header">
        <h1 class="arkiv-title"><?php the_title(); ?></h1>

        <?php if (!empty($terms) && !is_wp_error($terms)) : ?>
          <div class="arkiv-terms">
            <?php foreach ($terms as $term) : ?>
              <a class="arkiv-term" href="<?php echo esc_url(get_term_link($term)); ?>">
                <?php echo esc_html($term->name); ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </header>

      <?php if (has_post_thumbnail()) : ?>
        <figure class="arkiv-hero">
          <?php the_post_thumbnail('large'); ?>
        </figure>
      <?php endif; ?>

      <article class="arkiv-content">
        <?php the_content(); ?>
      </article>

      <?php if (!empty($gallery_ids)) : ?>
        <section class="arkiv-gallery">
          <h2>Billeder</h2>

          <div class="arkiv-grid">
            <?php foreach ($gallery_ids as $att_id) :
              $thumb = wp_get_attachment_image($att_id, 'medium_large', false, ['class' => 'arkiv-img']);
              $full  = wp_get_attachment_image_url($att_id, 'full');
              if (!$thumb || !$full) continue;
              ?>
              <a class="arkiv-tile arkiv-lightbox-trigger" href="<?php echo esc_url($full); ?>">
                <?php echo $thumb; ?>
              </a>
            <?php endforeach; ?>
          </div>

          <p class="arkiv-note">
            Klik på et billede for at åbne det i fuld størrelse.
          </p>
        </section>
      <?php endif; ?>

      <footer class="arkiv-footer">
        <a class="arkiv-back" href="<?php echo esc_url(home_url('/wordpress_D/arkiv/')); ?>">
          ← Tilbage til arkivet
        </a>
      </footer>

    </div>

    <div class="arkiv-lightbox" id="arkivLightbox">
      <span class="arkiv-lightbox-close" aria-label="Luk">&times;</span>
      <img src="" alt="">
    </div>
  </main>

  <style>
    .arkiv-single { padding: 24px 16px; }
    .arkiv-wrap { max-width: 1000px; margin: 0 auto; }

    .arkiv-title { margin: 0 0 10px; font-size: 34px; line-height: 1.15; }
    .arkiv-terms { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
    .arkiv-term {
      display: inline-flex; padding: 6px 12px; border-radius: 999px;
      background: #f2f2f2; text-decoration: none;
    }

    .arkiv-hero img { width: 100%; height: auto; border-radius: 14px; display:block; }

    .arkiv-content { margin-top: 18px; font-size: 16px; line-height: 1.7; }

    .arkiv-gallery { margin-top: 28px; }
    .arkiv-gallery h2 { margin: 0 0 12px; font-size: 22px; }

    .arkiv-grid { column-count: 3; column-gap: 14px; }
    @media (max-width: 1024px) { .arkiv-grid { column-count: 2; } }
    @media (max-width: 640px)  { .arkiv-grid { column-count: 1; } }

    .arkiv-tile { break-inside: avoid; display:block; margin: 0 0 14px; }
    .arkiv-img { width: 100%; height: auto; border-radius: 12px; display:block; }

    .arkiv-note { opacity: .75; margin-top: 10px; }

    .arkiv-footer { margin-top: 26px; padding-top: 16px; border-top: 1px solid #e5e5e5; }
    .arkiv-back {
      display: inline-flex; padding: 10px 14px; border-radius: 999px;
      background: #f2f2f2; text-decoration: none;
    }

    /* ===== Arkiv Lightbox ===== */
    .arkiv-lightbox {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.85);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }

    .arkiv-lightbox.active {
      display: flex;
    }

    .arkiv-lightbox img {
      max-width: 92vw;
      max-height: 92vh;
      border-radius: 12px;
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
    }

    .arkiv-lightbox-close {
      position: absolute;
      top: 20px;
      right: 20px;
      font-size: 32px;
      color: #fff;
      cursor: pointer;
      line-height: 1;
    }
  </style>

  <?php
endwhile; endif;

?>
<script>
(function () {
  const lightbox = document.getElementById('arkivLightbox');
  if (!lightbox) return;

  const img = lightbox.querySelector('img');
  const closeBtn = lightbox.querySelector('.arkiv-lightbox-close');

  document.querySelectorAll('.arkiv-lightbox-trigger').forEach(link => {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      img.src = this.getAttribute('href');
      lightbox.classList.add('active');
      document.body.style.overflow = 'hidden';
    });
  });

  function closeLightbox() {
    lightbox.classList.remove('active');
    img.src = '';
    document.body.style.overflow = '';
  }

  closeBtn.addEventListener('click', closeLightbox);

  lightbox.addEventListener('click', function (e) {
    if (e.target === lightbox) {
      closeLightbox();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeLightbox();
    }
  });
})();
</script>
<?php
get_footer();

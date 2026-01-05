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

      <?php
      $back_page_id = (int) get_option('arkiv_back_page_id', 0);
      $back_url = $back_page_id ? get_permalink($back_page_id) : '';
      if (!$back_url) {
        $back_url = home_url('/wordpress_D/arkiv/');
      }
      ?>
      <div class="arkiv-back-wrap">
        <a class="arkiv-back" href="<?php echo esc_url($back_url); ?>">
          ← Tilbage til arkivet
        </a>
      </div>

      <article class="arkiv-content">
        <?php the_content(); ?>
      </article>

      <?php if (!empty($gallery_ids)) : ?>
        <section class="arkiv-gallery">
          <h2>Billeder</h2>

          <div class="arkiv-grid">
            <?php foreach ($gallery_ids as $i => $att_id) :
              $thumb = wp_get_attachment_image($att_id, 'medium_large', false, ['class' => 'arkiv-img']);
              $full  = wp_get_attachment_image_url($att_id, 'full');
              if (!$thumb || !$full) continue;
              ?>
              <a class="arkiv-tile arkiv-lightbox-trigger" href="<?php echo esc_url($full); ?>" data-index="<?php echo (int)$i; ?>">
                <?php echo $thumb; ?>
              </a>
            <?php endforeach; ?>
          </div>

          <p class="arkiv-note">
            Klik på et billede for at åbne det i fuld størrelse.
          </p>
        </section>
      <?php endif; ?>

    </div>

    <?php if (comments_open() || get_comments_number()) : ?>
      <section class="arkiv-comments" id="arkivComments">
        <h2>Kommentarer</h2>

        <?php
        $comment_items = get_comments([
          'post_id' => $post_id,
          'status' => 'approve',
          'orderby' => 'comment_date_gmt',
          'order' => 'ASC',
        ]);
        ?>

        <?php if (!empty($comment_items)) : ?>
          <div class="arkiv-comments-list">
            <h3>Tidligere kommentarer</h3>
            <ol>
              <?php foreach ($comment_items as $comment_item) : ?>
                <li id="comment-<?php echo esc_attr($comment_item->comment_ID); ?>">
                  <article class="arkiv-comment">
                    <header>
                      <strong class="arkiv-comment-author">
                        <?php echo esc_html(get_comment_author($comment_item)); ?>
                      </strong>
                      <time class="arkiv-comment-date" datetime="<?php echo esc_attr(get_comment_date('c', $comment_item)); ?>">
                        <?php echo esc_html(get_comment_date('d/m/Y', $comment_item)); ?>
                        <?php echo esc_html(get_comment_time('H:i', $comment_item)); ?>
                      </time>
                    </header>
                    <div class="arkiv-comment-body">
                      <?php echo wp_kses_post(wpautop($comment_item->comment_content)); ?>
                    </div>
                  </article>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php endif; ?>

        <?php if (comments_open()) : ?>
          <div class="arkiv-comment-form">
            <?php
            comment_form([
              'title_reply' => 'Skriv en kommentar',
              'label_submit' => 'Send kommentar',
            ]);
            ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <!-- Arkiv lightbox start -->
    <div class="arkiv-lightbox" id="arkivLightbox" aria-hidden="true">
      <button class="arkiv-lightbox-close" type="button" aria-label="Luk">&times;</button>
      <button class="arkiv-lightbox-nav prev" type="button" aria-label="Forrige">&#10094;</button>
      <img src="" alt="">
      <button class="arkiv-lightbox-nav next" type="button" aria-label="Næste">&#10095;</button>
      <div class="arkiv-lightbox-counter" aria-live="polite"></div>
    </div>
    <!-- Arkiv lightbox end -->
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

    .arkiv-content { margin-top: 18px; font-size: 16px; line-height: 1.7; }

    .arkiv-gallery { margin-top: 28px; }
    .arkiv-gallery h2 { margin: 0 0 12px; font-size: 22px; }

    .arkiv-grid { column-count: 3; column-gap: 14px; }
    @media (max-width: 1024px) { .arkiv-grid { column-count: 2; } }
    @media (max-width: 640px)  { .arkiv-grid { column-count: 1; } }

    .arkiv-tile { break-inside: avoid; display:block; margin: 0 0 14px; }
    .arkiv-img { width: 100%; height: auto; border-radius: 12px; display:block; }

    .arkiv-note { opacity: .75; margin-top: 10px; }

    .arkiv-back-wrap { margin: 18px 0 8px; }
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
      touch-action: none;
    }

    .arkiv-lightbox.active {
      display: flex;
    }

    .arkiv-lightbox img {
      max-width: 92vw;
      max-height: 92vh;
      border-radius: 12px;
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
      cursor: zoom-in;
      transition: transform 0.15s ease;
    }

    .arkiv-lightbox-close {
      position: absolute;
      top: 20px;
      right: 20px;
      font-size: 32px;
      color: #fff;
      cursor: pointer;
      line-height: 1;
      background: transparent;
      border: none;
    }

    .arkiv-lightbox-nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      font-size: 48px;
      color: #fff;
      background: rgba(0,0,0,.35);
      border: none;
      width: 52px;
      height: 52px;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2;
    }

    .arkiv-lightbox-nav.prev { left: 20px; }
    .arkiv-lightbox-nav.next { right: 20px; }

    .arkiv-lightbox-counter {
      position: absolute;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      color: #fff;
      font-size: 14px;
      background: rgba(0,0,0,.35);
      padding: 6px 10px;
      border-radius: 999px;
    }

    .arkiv-lightbox.is-single .arkiv-lightbox-nav,
    .arkiv-lightbox.is-single .arkiv-lightbox-counter {
      display: none;
    }

    .arkiv-lightbox.is-zoomed img {
      cursor: grab;
    }

    .arkiv-lightbox.is-zoomed img:active {
      cursor: grabbing;
    }

    /* ===== Arkiv kommentarer ===== */
    .arkiv-comments {
      margin: 40px auto 0;
      max-width: 820px;
    }

    .arkiv-comments h2 {
      margin: 0 0 12px;
      font-size: 24px;
    }

    .arkiv-comments-list {
      background: #f7f7f7;
      border-radius: 12px;
      padding: 16px 18px;
      margin-top: 20px;
    }

    .arkiv-comments-list h3 {
      margin: 0 0 10px;
      font-size: 16px;
    }

    .arkiv-comments-list ol {
      margin: 0;
      padding-left: 18px;
    }

    .arkiv-comments-list li {
      margin-bottom: 14px;
    }

    .arkiv-comment {
      background: #fff;
      border-radius: 10px;
      padding: 12px 14px;
      box-shadow: 0 1px 2px rgba(0,0,0,.04);
    }

    .arkiv-comment header {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 12px;
      align-items: baseline;
      margin-bottom: 6px;
    }

    .arkiv-comment-date {
      display: inline-block;
      opacity: 0.7;
      font-size: 13px;
    }

    .arkiv-comment-form .comment-respond input[type="text"],
    .arkiv-comment-form .comment-respond input[type="email"],
    .arkiv-comment-form .comment-respond input[type="url"],
    .arkiv-comment-form .comment-respond textarea {
      width: 100%;
      max-width: 100%;
      border-radius: 8px;
      border: 1px solid #d9d9d9;
      padding: 10px 12px;
    }

    .arkiv-comment-form .comment-respond .form-submit input[type="submit"] {
      border: none;
      background: #111;
      color: #fff;
      padding: 10px 16px;
      border-radius: 999px;
      cursor: pointer;
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
  const prevBtn = lightbox.querySelector('.arkiv-lightbox-nav.prev');
  const nextBtn = lightbox.querySelector('.arkiv-lightbox-nav.next');
  const counter = lightbox.querySelector('.arkiv-lightbox-counter');

  const triggers = Array.from(document.querySelectorAll('.arkiv-lightbox-trigger'));
  const imageUrls = triggers.map(link => link.getAttribute('href'));
  const total = imageUrls.length;

  let currentIndex = 0;
  let isZoomed = false;
  let isDragging = false;
  let dragStartX = 0;
  let dragStartY = 0;
  let translateX = 0;
  let translateY = 0;
  let swipeStartX = 0;
  let swipeStartY = 0;
  let navLocked = false;

  function preload(url) {
    if (!url) return;
    const im = new Image();
    im.src = url;
  }

  function applyTransform() {
    const scale = isZoomed ? 2.5 : 1;
    img.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale})`;
  }

  function resetZoom() {
    isZoomed = false;
    isDragging = false;
    translateX = 0;
    translateY = 0;
    lightbox.classList.remove('is-zoomed');
    applyTransform();
  }

  function openLightbox(index) {
    currentIndex = index;
    lightbox.classList.add('active');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    show(currentIndex);
  }

  function closeLightbox() {
    lightbox.classList.remove('active');
    lightbox.setAttribute('aria-hidden', 'true');
    img.src = '';
    document.body.style.overflow = '';
    resetZoom();
  }

  function lockNav() {
    navLocked = true;
    setTimeout(() => {
      navLocked = false;
    }, 150);
  }

  function wrapIndex(index) {
    if (index < 0) return total - 1;
    if (index >= total) return 0;
    return index;
  }

  function show(index) {
    if (!total || navLocked) return;
    lockNav();
    const nextIndex = wrapIndex(index);
    currentIndex = nextIndex;
    const url = imageUrls[currentIndex];
    img.onerror = () => {
      if (total <= 1) {
        closeLightbox();
        return;
      }
      show(currentIndex + 1);
    };
    img.src = url;
    counter.textContent = `${currentIndex + 1} / ${total}`;
    preload(imageUrls[wrapIndex(currentIndex + 1)]);
    preload(imageUrls[wrapIndex(currentIndex - 1)]);
    resetZoom();
  }

  function toggleZoom() {
    if (!lightbox.classList.contains('active')) return;
    isZoomed = !isZoomed;
    lightbox.classList.toggle('is-zoomed', isZoomed);
    if (!isZoomed) {
      translateX = 0;
      translateY = 0;
    }
    applyTransform();
  }

  triggers.forEach(link => {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      const index = parseInt(this.dataset.index, 10) || 0;
      openLightbox(index);
    });
  });

  function goPrev() {
    show(currentIndex - 1);
  }

  function goNext() {
    show(currentIndex + 1);
  }

  prevBtn.addEventListener('click', goPrev);
  nextBtn.addEventListener('click', goNext);
  closeBtn.addEventListener('click', closeLightbox);

  lightbox.addEventListener('click', function (e) {
    if (e.target === lightbox) {
      closeLightbox();
    }
  });

  img.addEventListener('click', function (e) {
    e.stopPropagation();
    toggleZoom();
  });

  img.addEventListener('dblclick', function (e) {
    e.preventDefault();
    toggleZoom();
  });

  img.addEventListener('mousedown', function (e) {
    if (!isZoomed) return;
    isDragging = true;
    dragStartX = e.clientX - translateX;
    dragStartY = e.clientY - translateY;
  });

  document.addEventListener('mousemove', function (e) {
    if (!isZoomed || !isDragging) return;
    translateX = e.clientX - dragStartX;
    translateY = e.clientY - dragStartY;
    applyTransform();
  });

  document.addEventListener('mouseup', function () {
    isDragging = false;
  });

  img.addEventListener('touchstart', function (e) {
    if (!isZoomed) return;
    if (e.touches.length !== 1) return;
    isDragging = true;
    dragStartX = e.touches[0].clientX - translateX;
    dragStartY = e.touches[0].clientY - translateY;
  }, { passive: true });

  img.addEventListener('touchmove', function (e) {
    if (!isZoomed || !isDragging) return;
    if (e.touches.length !== 1) return;
    translateX = e.touches[0].clientX - dragStartX;
    translateY = e.touches[0].clientY - dragStartY;
    applyTransform();
    e.preventDefault();
  }, { passive: false });

  img.addEventListener('touchend', function () {
    isDragging = false;
  });

  lightbox.addEventListener('touchstart', function (e) {
    if (isZoomed) return;
    if (e.touches.length !== 1) return;
    swipeStartX = e.touches[0].clientX;
    swipeStartY = e.touches[0].clientY;
  }, { passive: true });

  lightbox.addEventListener('touchend', function (e) {
    if (isZoomed) return;
    if (!swipeStartX && !swipeStartY) return;
    const touch = e.changedTouches[0];
    const deltaX = touch.clientX - swipeStartX;
    const deltaY = touch.clientY - swipeStartY;
    swipeStartX = 0;
    swipeStartY = 0;
    if (Math.abs(deltaX) < 50 || Math.abs(deltaX) < Math.abs(deltaY)) return;
    if (deltaX < 0) {
      goNext();
    } else {
      goPrev();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (!lightbox.classList.contains('active')) return;
    if (e.key === 'Escape') {
      closeLightbox();
    }
    if (e.key === 'ArrowLeft') {
      goPrev();
    }
    if (e.key === 'ArrowRight') {
      goNext();
    }
  });

  lightbox.classList.toggle('is-single', total <= 1);
})();
</script>
<?php
get_footer();

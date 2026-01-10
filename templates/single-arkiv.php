<?php
// Plugin template: Single Arkiv
get_header();

if (have_posts()) : while (have_posts()) : the_post();

  $post_id = get_the_ID();
  $author_id = (int) get_post_field('post_author', $post_id);
  $author = $author_id ? get_userdata($author_id) : null;
  $author_name = $author ? $author->display_name : '';
  $is_author = is_user_logged_in() && (int) get_current_user_id() === $author_id;
  $is_edit = $is_author && !empty($_GET['arkiv_edit']);
  $edit_url = add_query_arg('arkiv_edit', '1', get_permalink($post_id));

  // Taxonomy terms (Mappe)
  $terms = get_the_terms($post_id, 'mappe');
  $mappe_terms = get_terms([
    'taxonomy' => 'mappe',
    'hide_empty' => false,
  ]);
  $current_mappe_id = (!empty($terms) && !is_wp_error($terms)) ? (int) $terms[0]->term_id : 0;

  // Galleri IDs (som du gemmer i dit plugin)
  $gallery_ids = get_post_meta($post_id, '_arkiv_gallery_ids', true);
  if (!is_array($gallery_ids)) {
    $gallery_ids = [];
  }
  $pdf_ids = get_post_meta($post_id, '_arkiv_pdf_ids', true);
  if (!is_array($pdf_ids)) {
    $pdf_ids = [];
  }
  $thumbnail_id = get_post_thumbnail_id($post_id);
  $thumbnail_candidates = array_merge($gallery_ids, $pdf_ids);
  $featured_selected = ($thumbnail_id && in_array((int) $thumbnail_id, $thumbnail_candidates, true)) ? (int) $thumbnail_id : 'auto';
  ?>

  <main class="arkiv-single">
    <div class="arkiv-wrap">

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
        <?php if (!empty($terms) && !is_wp_error($terms)) : ?>
          <?php $primary_term = $terms[0]; ?>
          <a class="arkiv-back" href="<?php echo esc_url(get_term_link($primary_term)); ?>">
            ← Tilbage til <?php echo esc_html($primary_term->name); ?>
          </a>
        <?php endif; ?>
      </div>

      <header class="arkiv-header">
        <h1 class="arkiv-title"><?php the_title(); ?></h1>
        <?php if ($author_name !== '') : ?>
          <p class="arkiv-author">Forfatter: <?php echo esc_html($author_name); ?></p>
        <?php endif; ?>
        <?php if ($is_author && !$is_edit) : ?>
          <a class="arkiv-edit-button" href="<?php echo esc_url($edit_url); ?>">Rediger</a>
        <?php endif; ?>
      </header>

      <?php if ($is_edit) : ?>
        <section class="arkiv-edit">
          <form class="arkiv-edit-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url($edit_url); ?>">
            <?php wp_nonce_field('arkiv_edit_action', 'arkiv_edit_nonce'); ?>
            <?php wp_nonce_field('arkiv_submit_action', 'arkiv_submit_nonce'); ?>
            <input type="hidden" name="arkiv_edit_post_id" value="<?php echo (int) $post_id; ?>">
            <input type="hidden" name="arkiv_delete_images" id="arkivDeleteImages" value="">
            <input type="hidden" name="arkiv_delete_pdfs" id="arkivDeletePdfs" value="">

            <label class="arkiv-edit-label" for="arkivEditTitle">Titel</label>
            <input
              id="arkivEditTitle"
              class="arkiv-edit-title"
              type="text"
              name="arkiv_edit_title"
              value="<?php echo esc_attr(get_the_title($post_id)); ?>"
              required
            >

            <label class="arkiv-edit-label" for="arkivEditContent">Historie</label>
            <?php
            wp_editor(
              get_post_field('post_content', $post_id),
              'arkivEditContent',
              [
                'textarea_name' => 'arkiv_edit_content',
                'textarea_rows' => 10,
                'media_buttons' => false,
                'teeny' => true,
              ]
            );
            ?>

            <div class="arkiv-edit-mappe">
              <label class="arkiv-edit-label" for="arkivEditMappe">Mappe</label>
              <select id="arkivEditMappe" name="arkiv_edit_mappe" class="arkiv-edit-select">
                <option value="0">Ingen mappe</option>
                <?php if (!is_wp_error($mappe_terms)) : ?>
                  <?php foreach ($mappe_terms as $term) : ?>
                    <option value="<?php echo (int) $term->term_id; ?>" <?php selected($current_mappe_id, (int) $term->term_id); ?>>
                      <?php echo esc_html($term->name); ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

            <div class="arkiv-edit-images">
              <h2>Billeder</h2>
              <?php if (!empty($gallery_ids)) : ?>
                <div class="arkiv-edit-grid">
                  <?php foreach ($gallery_ids as $att_id) :
                    $thumb = wp_get_attachment_image($att_id, 'medium', false, ['class' => 'arkiv-img']);
                    if (!$thumb) continue;
                    ?>
                    <div class="arkiv-edit-tile" data-att-id="<?php echo (int) $att_id; ?>">
                      <?php echo $thumb; ?>
                      <button class="arkiv-delete-image" type="button">Slet</button>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else : ?>
                <p class="arkiv-edit-empty">Ingen billeder endnu.</p>
              <?php endif; ?>
            </div>

            <div class="arkiv-edit-images" style="margin-top:18px;">
              <h2>PDF-dokumenter</h2>
              <?php if (!empty($pdf_ids)) : ?>
                <div class="arkiv-edit-grid">
                  <?php foreach ($pdf_ids as $att_id) :
                    $thumb = wp_get_attachment_image($att_id, 'medium', false, ['class' => 'arkiv-pdf-thumb']);
                    if (!$thumb) {
                      $icon = wp_mime_type_icon($att_id);
                      $thumb = $icon ? '<img class="arkiv-pdf-thumb" src="' . esc_url($icon) . '" alt="">' : '';
                    }
                    if (!$thumb) continue;
                    $title = get_the_title($att_id);
                    ?>
                    <div class="arkiv-edit-tile" data-pdf-id="<?php echo (int) $att_id; ?>">
                      <?php echo $thumb; ?>
                      <?php if ($title !== '') : ?>
                        <div class="arkiv-edit-caption"><?php echo esc_html($title); ?></div>
                      <?php endif; ?>
                      <button class="arkiv-delete-pdf" type="button">Slet</button>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else : ?>
                <p class="arkiv-edit-empty">Ingen PDF'er endnu.</p>
              <?php endif; ?>
            </div>

            <div class="arkiv-edit-upload">
              <label class="arkiv-edit-label" for="arkivEditImages">Upload flere billeder</label>
              <input id="arkivEditImages" type="file" name="arkiv_images[]" accept="image/*" multiple data-max-files="50">
              <div class="arkiv-edit-preview" id="arkivEditPreview"></div>
            </div>

            <div class="arkiv-edit-upload">
              <label class="arkiv-edit-label" for="arkivEditPdfs">Upload flere PDF'er</label>
              <input id="arkivEditPdfs" type="file" name="arkiv_pdfs[]" accept="application/pdf" multiple data-max-files="20">
              <div class="arkiv-edit-preview" id="arkivEditPdfPreview"></div>
            </div>
            <p class="arkiv-edit-status" id="arkivEditUploadStatus" aria-live="polite">
              <span class="arkiv-edit-status-text"></span>
              <span class="arkiv-edit-spinner" aria-hidden="true"></span>
            </p>

            <div class="arkiv-edit-featured">
              <label class="arkiv-edit-label" for="arkivFeaturedImage">Forsidebillede</label>
              <?php if (!empty($gallery_ids) || !empty($pdf_ids)) : ?>
                <select id="arkivFeaturedImage" name="arkiv_featured_image">
                  <option value="auto" <?php selected($featured_selected, 'auto'); ?>>Automatisk (første fil)</option>
                  <option value="0">Ingen forsidebillede</option>
                  <?php foreach ($gallery_ids as $att_id) :
                    $label = get_the_title($att_id);
                    if ($label === '') {
                      $label = 'Billede #' . (int) $att_id;
                    }
                    ?>
                    <option value="<?php echo (int) $att_id; ?>" <?php selected($featured_selected, (int) $att_id); ?>>
                      <?php echo esc_html($label); ?>
                    </option>
                  <?php endforeach; ?>
                  <?php foreach ($pdf_ids as $att_id) :
                    $label = get_the_title($att_id);
                    if ($label === '') {
                      $label = 'PDF #' . (int) $att_id;
                    }
                    $label = 'PDF: ' . $label;
                    ?>
                    <option value="<?php echo (int) $att_id; ?>" <?php selected($featured_selected, (int) $att_id); ?>>
                      <?php echo esc_html($label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php else : ?>
                <p class="arkiv-edit-empty">Tilføj billeder eller PDF'er for at vælge forsidebillede.</p>
              <?php endif; ?>
            </div>

            <div class="arkiv-edit-actions">
              <button type="submit" name="arkiv_edit_btn" value="1" class="arkiv-edit-save">Gem</button>
              <a class="arkiv-edit-cancel" href="<?php echo esc_url(get_permalink($post_id)); ?>">Annuler</a>
              <button type="submit" name="arkiv_delete_btn" value="1" class="arkiv-edit-delete">Slet indlæg</button>
            </div>
          </form>
        </section>
      <?php else : ?>
        <article class="arkiv-content">
          <?php the_content(); ?>
        </article>
      <?php endif; ?>

      <?php if (!$is_edit && !empty($gallery_ids)) : ?>
        <section class="arkiv-gallery">
          <h2>Billeder</h2>

          <div class="arkiv-grid">
            <?php foreach ($gallery_ids as $i => $att_id) :
              $thumb = wp_get_attachment_image($att_id, 'medium_large', false, ['class' => 'arkiv-img']);
              $full  = wp_get_attachment_image_url($att_id, 'full');
              if (!$thumb || !$full) continue;
              ?>
              <a class="arkiv-tile arkiv-lightbox-trigger" href="<?php echo esc_url($full); ?>" data-index="<?php echo (int)$i; ?>" data-type="image" data-src="<?php echo esc_url($full); ?>">
                <?php echo $thumb; ?>
              </a>
            <?php endforeach; ?>
          </div>

          <p class="arkiv-note">
            Klik på et billede for at åbne det i fuld størrelse.
          </p>
        </section>
      <?php endif; ?>

      <?php if (!$is_edit && !empty($pdf_ids)) : ?>
        <section class="arkiv-gallery">
          <h2>Dokumenter</h2>

          <div class="arkiv-grid">
            <?php foreach ($pdf_ids as $i => $att_id) :
              $thumb = wp_get_attachment_image($att_id, 'medium_large', false, ['class' => 'arkiv-img']);
              if (!$thumb) {
                $icon = wp_mime_type_icon($att_id);
                $thumb = $icon ? '<img class="arkiv-img" src="' . esc_url($icon) . '" alt="">' : '';
              }
              if (!$thumb) continue;
              $pdf_url = wp_get_attachment_url($att_id);
              if (!$pdf_url) continue;
              ?>
              <a class="arkiv-tile arkiv-lightbox-trigger" href="<?php echo esc_url($pdf_url); ?>" data-index="<?php echo (int) (count($gallery_ids) + $i); ?>" data-type="pdf" data-src="<?php echo esc_url($pdf_url); ?>">
                <?php echo $thumb; ?>
              </a>
            <?php endforeach; ?>
          </div>

          <p class="arkiv-note">
            Klik på et dokument for at åbne det.
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
            <?php if (is_user_logged_in()) : ?>
              <?php
              comment_form([
                'title_reply' => 'Skriv en kommentar',
                'label_submit' => 'Send kommentar',
              ]);
              ?>
            <?php else : ?>
              <p>Du skal være <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">logget ind</a> for at kommentere.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <!-- Arkiv lightbox start -->
    <div class="arkiv-lightbox" id="arkivLightbox" aria-hidden="true">
      <button class="arkiv-lightbox-close" type="button" aria-label="Luk">&times;</button>
      <button class="arkiv-lightbox-nav prev" type="button" aria-label="Forrige">&#10094;</button>
      <div class="arkiv-lightbox-media">
        <img src="" alt="">
        <iframe class="arkiv-lightbox-pdf" title="PDF" loading="lazy"></iframe>
      </div>
      <button class="arkiv-lightbox-nav next" type="button" aria-label="Næste">&#10095;</button>
      <div class="arkiv-lightbox-counter" aria-live="polite"></div>
    </div>
    <!-- Arkiv lightbox end -->
  </main>

  <style>
    .arkiv-single { padding: 24px 16px; }
    .arkiv-wrap { max-width: 1000px; margin: 0 auto; }

    .arkiv-title { margin: 0 0 10px; font-size: 34px; line-height: 1.15; }
    .arkiv-author { margin: 0 0 18px; color: #666; font-size: 14px; }
    .arkiv-content { margin-top: 18px; font-size: 16px; line-height: 1.7; }
    .arkiv-edit-button {
      display: inline-flex;
      padding: 10px 18px;
      border-radius: 999px;
      background: #9a8c98;
      color: #fff;
      text-decoration: none;
      font-size: 15px;
      margin-top: 8px;
      align-items: center;
      gap: 6px;
      cursor: pointer;
    }

    .arkiv-gallery { margin-top: 28px; }
    .arkiv-gallery h2 { margin: 0 0 12px; font-size: 22px; }

    .arkiv-grid { column-count: 3; column-gap: 14px; }
    @media (max-width: 1024px) { .arkiv-grid { column-count: 2; } }
    @media (max-width: 640px)  { .arkiv-grid { column-count: 1; } }

    .arkiv-tile { break-inside: avoid; display:block; margin: 0 0 14px; }
    .arkiv-img { width: 100%; height: auto; border-radius: 12px; display:block; }

    .arkiv-note { opacity: .75; margin-top: 10px; }

    .arkiv-back-wrap { margin: 0 0 18px; display: flex; flex-wrap: wrap; gap: 10px; }
    .arkiv-back {
      display: inline-flex; padding: 10px 14px; border-radius: 999px;
      background: #f2f2f2; text-decoration: none;
    }

    .arkiv-edit {
      margin-top: 18px;
      background: #f7f7f7;
      padding: 18px;
      border-radius: 16px;
    }

    .arkiv-edit-form textarea {
      width: 100%;
      max-width: 100%;
      border-radius: 12px;
      border: 1px solid #d9d9d9;
      padding: 12px;
      font-size: 15px;
      line-height: 1.6;
      background: #fff;
    }

    .arkiv-edit-title {
      width: 100%;
      max-width: 100%;
      border-radius: 12px;
      border: 1px solid #d9d9d9;
      padding: 10px 12px;
      font-size: 16px;
      line-height: 1.4;
      background: #fff;
      margin-bottom: 6px;
    }

    .arkiv-edit-label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .arkiv-edit-images {
      margin-top: 18px;
    }

    .arkiv-edit-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 14px;
    }

    .arkiv-edit-tile {
      position: relative;
      background: #fff;
      padding: 10px;
      border-radius: 12px;
      box-shadow: 0 1px 2px rgba(0,0,0,.05);
    }

    .arkiv-delete-image {
      position: absolute;
      top: 8px;
      right: 8px;
      background: rgba(17,17,17,.9);
      color: #fff;
      border: none;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 12px;
      cursor: pointer;
    }

    .arkiv-delete-pdf {
      position: absolute;
      top: 8px;
      right: 8px;
      background: rgba(17,17,17,.9);
      color: #fff;
      border: none;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 12px;
      cursor: pointer;
    }

    .arkiv-pdf-thumb {
      width: 100%;
      height: auto;
      border-radius: 12px;
      display: block;
    }

    .arkiv-edit-caption {
      margin-top: 6px;
      font-size: 12px;
      opacity: 0.8;
      word-break: break-word;
    }

    .arkiv-edit-empty {
      margin: 8px 0 0;
      opacity: .7;
    }

    .arkiv-edit-upload {
      margin-top: 18px;
    }

    .arkiv-edit-featured {
      margin-top: 18px;
    }

    .arkiv-edit-select,
    .arkiv-edit-featured select {
      width: 100%;
      max-width: 100%;
      border-radius: 12px;
      border: 1px solid #d9d9d9;
      padding: 10px 12px;
      font-size: 15px;
      line-height: 1.4;
      min-height: 44px;
      background: #fff;
    }

    .arkiv-edit-preview {
      margin-top: 12px;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 12px;
    }

    .arkiv-edit-preview img {
      width: 100%;
      height: auto;
      border-radius: 12px;
    }

    .arkiv-edit-pdf {
      padding: 10px;
      border-radius: 10px;
      background: #fff;
      border: 1px dashed #d9d9d9;
      font-size: 12px;
      word-break: break-word;
    }

    .arkiv-edit-preview .arkiv-upload-item {
      background: #f7f7f7;
      border-radius: 12px;
      padding: 8px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      box-shadow: 0 1px 2px rgba(0,0,0,.05);
    }

    .arkiv-edit-preview .arkiv-upload-thumb {
      width: 100%;
      border-radius: 10px;
      object-fit: cover;
      background: #fff;
    }

    .arkiv-edit-preview .arkiv-upload-pdf {
      width: 100%;
      min-height: 120px;
      border-radius: 10px;
      background: #fff;
      border: 1px dashed #d8d8d8;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      font-weight: 700;
      color: #d63638;
    }

    .arkiv-edit-preview .arkiv-upload-name {
      font-size: 12px;
      color: #333;
      word-break: break-word;
    }

    .arkiv-edit-preview .arkiv-upload-bar {
      position: relative;
      height: 6px;
      border-radius: 999px;
      background: #e1e1e1;
      overflow: hidden;
    }

    .arkiv-edit-preview .arkiv-upload-bar span {
      position: absolute;
      left: 0;
      top: 0;
      height: 100%;
      width: 0%;
      background: #111;
      transition: width 0.2s ease;
    }

    .arkiv-edit-preview .arkiv-upload-bar.is-error span {
      background: #dc3232;
    }

    .arkiv-edit-preview .arkiv-upload-state {
      font-size: 12px;
      color: #555;
    }

    .arkiv-edit-preview .arkiv-upload-state.is-error {
      color: #dc3232;
    }

    .arkiv-edit-status {
      margin-top: 10px;
      font-size: 13px;
      color: #555;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .arkiv-edit-spinner {
      width: 14px;
      height: 14px;
      border: 2px solid rgba(17, 17, 17, 0.2);
      border-top-color: rgba(17, 17, 17, 0.7);
      border-radius: 50%;
      animation: arkiv-spin 0.8s linear infinite;
      display: none;
    }

    .arkiv-edit-status.is-busy .arkiv-edit-spinner {
      display: inline-block;
    }

    .arkiv-edit-form.is-uploading .arkiv-edit-actions button,
    .arkiv-edit-form.is-uploading .arkiv-edit-actions a {
      pointer-events: none;
      opacity: 0.6;
    }

    .arkiv-edit-actions {
      display: flex;
      gap: 12px;
      margin-top: 22px;
      align-items: center;
    }

    .arkiv-edit-save {
      border: none;
      background: #111;
      color: #fff;
      padding: 10px 18px;
      border-radius: 999px;
      cursor: pointer;
      font-size: 15px;
    }

    .arkiv-edit-cancel {
      padding: 10px 18px;
      border-radius: 999px;
      background: #e8e8e8;
      text-decoration: none;
      color: #111;
      font-size: 15px;
    }

    .arkiv-edit-delete {
      margin-left: auto;
      padding: 10px 18px;
      border-radius: 999px;
      background: #d63638;
      color: #fff;
      border: none;
      cursor: pointer;
      font-size: 15px;
    }

    @keyframes arkiv-spin {
      to {
        transform: rotate(360deg);
      }
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

    .arkiv-lightbox-media {
      position: relative;
      max-width: 92vw;
      max-height: 92vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .arkiv-lightbox img {
      max-width: 92vw;
      max-height: 92vh;
      border-radius: 12px;
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
      cursor: zoom-in;
      transition: transform 0.15s ease;
    }

    .arkiv-lightbox-pdf {
      width: 92vw;
      height: 92vh;
      border: none;
      border-radius: 12px;
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
      background: #111;
      display: none;
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
  const form = document.querySelector('.arkiv-edit-form');
  const deleteButtons = document.querySelectorAll('.arkiv-delete-image');
  const deleteInput = document.getElementById('arkivDeleteImages');
  const deletePdfButtons = document.querySelectorAll('.arkiv-delete-pdf');
  const deletePdfInput = document.getElementById('arkivDeletePdfs');
  const uploadInput = document.getElementById('arkivEditImages');
  const previewWrap = document.getElementById('arkivEditPreview');
  const pdfUploadInput = document.getElementById('arkivEditPdfs');
  const pdfPreviewWrap = document.getElementById('arkivEditPdfPreview');
  const statusEl = document.getElementById('arkivEditUploadStatus');
  const statusText = statusEl ? statusEl.querySelector('.arkiv-edit-status-text') : null;
  const deletePostButton = document.querySelector('.arkiv-edit-delete');
  let imageMeta = [];
  let pdfMeta = [];

  if (deleteButtons.length && deleteInput) {
    deleteButtons.forEach(button => {
      button.addEventListener('click', function () {
        const tile = this.closest('.arkiv-edit-tile');
        if (!tile) return;
        const attId = tile.dataset.attId;
        if (!attId) return;
        const confirmDelete = window.confirm('vil du virkelig slette billedet! ja/nej');
        if (!confirmDelete) return;
        tile.classList.add('is-deleting');
        tile.style.opacity = '0.4';
        const current = deleteInput.value ? deleteInput.value.split(',') : [];
        if (!current.includes(attId)) {
          current.push(attId);
          deleteInput.value = current.join(',');
        }
        tile.remove();
      });
    });
  }

  function setStatus(message, busy = false) {
    if (!statusEl || !statusText) return;
    statusText.textContent = message || '';
    statusEl.classList.toggle('is-busy', Boolean(busy));
  }

  function isPdfFile(file) {
    if (!file) return false;
    if (file.type === 'application/pdf') return true;
    return file.name && file.name.toLowerCase().endsWith('.pdf');
  }

  function updateProgress(meta, loaded) {
    const size = meta.size || 0;
    const percent = size ? Math.round((loaded / size) * 100) : 100;
    meta.bar.style.width = `${Math.min(100, percent)}%`;
  }

  function setItemState(meta, state, isError = false) {
    meta.state.textContent = state;
    meta.state.classList.toggle('is-error', isError);
    meta.barWrap.classList.toggle('is-error', isError);
  }

  function buildPreview(files, config) {
    const wrap = config.wrap;
    const isPdf = config.isPdf;
    const maxFiles = config.maxFiles;
    wrap.innerHTML = '';
    const metaList = [];

    if (!files.length) {
      setStatus('');
      return metaList;
    }

    if (files.length > maxFiles) {
      setStatus(`Du kan max uploade ${maxFiles} filer ad gangen.`);
    } else {
      setStatus('');
    }

    files.slice(0, maxFiles).forEach(file => {
      const item = document.createElement('div');
      item.className = 'arkiv-upload-item';

      if (isPdf) {
        const pdfBadge = document.createElement('div');
        pdfBadge.className = 'arkiv-upload-pdf';
        pdfBadge.textContent = 'PDF';
        item.appendChild(pdfBadge);
      } else {
        const img = document.createElement('img');
        img.className = 'arkiv-upload-thumb';
        img.alt = file.name;
        item.appendChild(img);
      }

      const name = document.createElement('div');
      name.className = 'arkiv-upload-name';
      name.textContent = file.name;
      item.appendChild(name);

      const barWrap = document.createElement('div');
      barWrap.className = 'arkiv-upload-bar';
      const bar = document.createElement('span');
      barWrap.appendChild(bar);
      item.appendChild(barWrap);

      const state = document.createElement('div');
      state.className = 'arkiv-upload-state';
      state.textContent = 'Venter';
      item.appendChild(state);

      wrap.appendChild(item);

      if (!isPdf && file.type.startsWith('image/')) {
        const img = item.querySelector('img');
        if (img) {
          const reader = new FileReader();
          reader.onload = event => {
            img.src = event.target.result;
          };
          reader.readAsDataURL(file);
        }
      }

      metaList.push({
        file,
        size: file.size || 0,
        bar,
        barWrap,
        state,
      });
    });

    return metaList;
  }

  function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  function uploadSingleAttempt(meta, postId, nonce, action, fieldName) {
    return new Promise(resolve => {
      const formData = new FormData();
      formData.append('action', action);
      formData.append('post_id', postId);
      formData.append('arkiv_submit_nonce', nonce);
      formData.append(fieldName, meta.file);

      const xhr = new XMLHttpRequest();
      xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
      xhr.responseType = 'json';

      xhr.upload.addEventListener('progress', function (event) {
        if (!event.lengthComputable) return;
        updateProgress(meta, event.loaded);
      });

      xhr.addEventListener('load', function () {
        if (xhr.status >= 200 && xhr.status < 300 && xhr.response && xhr.response.success) {
          updateProgress(meta, meta.size || 0);
          resolve(true);
          return;
        }
        resolve(false);
      });

      xhr.addEventListener('error', function () {
        resolve(false);
      });

      xhr.send(formData);
    });
  }

  async function uploadSingleFile(meta, postId, nonce, action, fieldName) {
    const maxRetries = 10;
    for (let attempt = 0; attempt <= maxRetries; attempt++) {
      if (attempt > 0) {
        setItemState(meta, `Prøver igen (${attempt}/${maxRetries})`, true);
        setStatus(`Upload fejlede. Prøver igen (${attempt}/${maxRetries})...`, true);
        await wait(500);
      }
      setItemState(meta, 'Uploader');
      const ok = await uploadSingleAttempt(meta, postId, nonce, action, fieldName);
      if (ok) {
        setItemState(meta, 'Færdig');
        return true;
      }
    }
    setItemState(meta, 'fejl', true);
    return false;
  }

  if (uploadInput && previewWrap) {
    uploadInput.addEventListener('change', function () {
      const files = Array.from(uploadInput.files || []).filter(file => file.type.startsWith('image/'));
      const maxFiles = parseInt(uploadInput.dataset.maxFiles, 10) || 50;
      imageMeta = buildPreview(files, { wrap: previewWrap, maxFiles, isPdf: false });
    });
  }

  if (deletePdfButtons.length && deletePdfInput) {
    deletePdfButtons.forEach(button => {
      button.addEventListener('click', function () {
        const tile = this.closest('.arkiv-edit-tile');
        if (!tile) return;
        const attId = tile.dataset.pdfId;
        if (!attId) return;
        const confirmDelete = window.confirm('vil du virkelig slette pdf’en! ja/nej');
        if (!confirmDelete) return;
        tile.classList.add('is-deleting');
        tile.style.opacity = '0.4';
        const current = deletePdfInput.value ? deletePdfInput.value.split(',') : [];
        if (!current.includes(attId)) {
          current.push(attId);
          deletePdfInput.value = current.join(',');
        }
        tile.remove();
      });
    });
  }

  if (pdfUploadInput && pdfPreviewWrap) {
    pdfUploadInput.addEventListener('change', function () {
      const files = Array.from(pdfUploadInput.files || []).filter(file => isPdfFile(file));
      const maxFiles = parseInt(pdfUploadInput.dataset.maxFiles, 10) || 20;
      pdfMeta = buildPreview(files, { wrap: pdfPreviewWrap, maxFiles, isPdf: true });
    });
  }

  if (form && uploadInput && pdfUploadInput) {
    form.addEventListener('submit', function (event) {
      if (!window.FormData || !window.XMLHttpRequest) {
        return;
      }

      const files = Array.from(uploadInput.files || []).filter(file => file.type.startsWith('image/'));
      const pdfFiles = Array.from(pdfUploadInput.files || []).filter(file => isPdfFile(file));
      const maxImageFiles = parseInt(uploadInput.dataset.maxFiles, 10) || 50;
      const maxPdfFiles = parseInt(pdfUploadInput.dataset.maxFiles, 10) || 20;

      if (files.length > maxImageFiles) {
        event.preventDefault();
        setStatus(`Du kan max uploade ${maxImageFiles} filer ad gangen.`);
        return;
      }
      if (pdfFiles.length > maxPdfFiles) {
        event.preventDefault();
        setStatus(`Du kan max uploade ${maxPdfFiles} filer ad gangen.`);
        return;
      }

      event.preventDefault();
      form.classList.add('is-uploading');
      setStatus('Gemmer ændringer...', true);

      if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
        window.tinymce.triggerSave();
      }

      const formData = new FormData(form);
      const submitter = event.submitter;
      if (submitter && submitter.name) {
        formData.append(submitter.name, submitter.value || '1');
      }
      formData.delete('arkiv_images[]');
      formData.delete('arkiv_pdfs[]');

      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);
      xhr.responseType = 'text';

      xhr.addEventListener('load', async function () {
        if (!(xhr.status >= 200 && xhr.status < 400)) {
          form.classList.remove('is-uploading');
          setStatus('Noget gik galt. Prøv igen.');
          return;
        }

        const postId = form.querySelector('[name="arkiv_edit_post_id"]')?.value || '';
        const nonce = form.querySelector('[name="arkiv_submit_nonce"]')?.value || '';
        let hadError = false;

        for (let i = 0; i < imageMeta.length; i++) {
          const meta = imageMeta[i];
          setItemState(meta, 'Uploader');
          setStatus(`Uploader billede ${i + 1} af ${imageMeta.length}...`, true);
          const ok = await uploadSingleFile(meta, postId, nonce, 'arkiv_upload_image', 'arkiv_single_image');
          if (!ok) {
            hadError = true;
          }
        }

        for (let i = 0; i < pdfMeta.length; i++) {
          const meta = pdfMeta[i];
          setItemState(meta, 'Uploader');
          setStatus(`Uploader PDF ${i + 1} af ${pdfMeta.length}...`, true);
          const ok = await uploadSingleFile(meta, postId, nonce, 'arkiv_upload_pdf', 'arkiv_single_pdf');
          if (!ok) {
            hadError = true;
          }
        }

        form.classList.remove('is-uploading');

        if (hadError) {
          setStatus('Nogle filer fejlede. Prøv igen.');
          return;
        }

        const redirectUrl = new URL(form.action, window.location.href);
        redirectUrl.searchParams.delete('arkiv_edit');
        setStatus('Færdig', true);
        window.location.href = redirectUrl.toString();
      });

      xhr.addEventListener('error', function () {
        form.classList.remove('is-uploading');
        setStatus('Noget gik galt. Prøv igen.');
      });

      xhr.send(formData);
    });
  }

  if (deletePostButton) {
    deletePostButton.addEventListener('click', function (event) {
      const confirmed = window.confirm('Vil du virkelig slette indlægget?');
      if (!confirmed) {
        event.preventDefault();
      }
    });
  }

  const lightbox = document.getElementById('arkivLightbox');
  if (!lightbox) return;

  const img = lightbox.querySelector('img');
  const pdfFrame = lightbox.querySelector('.arkiv-lightbox-pdf');
  const closeBtn = lightbox.querySelector('.arkiv-lightbox-close');
  const prevBtn = lightbox.querySelector('.arkiv-lightbox-nav.prev');
  const nextBtn = lightbox.querySelector('.arkiv-lightbox-nav.next');
  const counter = lightbox.querySelector('.arkiv-lightbox-counter');

  const triggers = Array.from(document.querySelectorAll('.arkiv-lightbox-trigger'));
  const items = triggers.map((link, index) => {
    link.dataset.index = index;
    return {
      type: link.dataset.type || 'image',
      src: link.dataset.src || link.getAttribute('href'),
    };
  });
  const total = items.length;

  let currentIndex = 0;
  let currentType = 'image';
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
    if (pdfFrame) {
      pdfFrame.src = '';
    }
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
    const item = items[currentIndex];
    currentType = item.type;
    if (currentType === 'pdf' && pdfFrame) {
      img.style.display = 'none';
      pdfFrame.style.display = 'block';
      pdfFrame.src = item.src;
      lightbox.classList.add('is-pdf');
    } else {
      img.style.display = 'block';
      if (pdfFrame) {
        pdfFrame.style.display = 'none';
        pdfFrame.src = '';
      }
      lightbox.classList.remove('is-pdf');
      img.onerror = () => {
        if (total <= 1) {
          closeLightbox();
          return;
        }
        show(currentIndex + 1);
      };
      img.src = item.src;
    }
    counter.textContent = `${currentIndex + 1} / ${total}`;
    const nextItem = items[wrapIndex(currentIndex + 1)];
    const prevItem = items[wrapIndex(currentIndex - 1)];
    if (nextItem && nextItem.type === 'image') {
      preload(nextItem.src);
    }
    if (prevItem && prevItem.type === 'image') {
      preload(prevItem.src);
    }
    resetZoom();
  }

  function toggleZoom() {
    if (!lightbox.classList.contains('active')) return;
    if (currentType !== 'image') return;
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
    if (currentType !== 'image') return;
    if (!isZoomed) return;
    isDragging = true;
    dragStartX = e.clientX - translateX;
    dragStartY = e.clientY - translateY;
  });

  document.addEventListener('mousemove', function (e) {
    if (currentType !== 'image') return;
    if (!isZoomed || !isDragging) return;
    translateX = e.clientX - dragStartX;
    translateY = e.clientY - dragStartY;
    applyTransform();
  });

  document.addEventListener('mouseup', function () {
    isDragging = false;
  });

  img.addEventListener('touchstart', function (e) {
    if (currentType !== 'image') return;
    if (!isZoomed) return;
    if (e.touches.length !== 1) return;
    isDragging = true;
    dragStartX = e.touches[0].clientX - translateX;
    dragStartY = e.touches[0].clientY - translateY;
  }, { passive: true });

  img.addEventListener('touchmove', function (e) {
    if (currentType !== 'image') return;
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

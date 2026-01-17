<?php
/**
 * Plugin Name: Arkiv Submission (Community)
 * Description: Frontend indsendelse af Arkiv-indlæg med multiple billeduploads + moderation (pending).
 * Version: 1.0.0
 * Author: Youhjjk
 */

if (!defined('ABSPATH')) {
  exit;
}

class Arkiv_Submission_Plugin {
  const CPT = 'arkiv';
  const TAX = 'mappe'; // <-- ret hvis din taxonomy slug er anderledes
  const META_SUGGESTED_FOLDER = '_arkiv_suggested_folder';
  const META_MAPPE_IMAGE_ID = '_arkiv_mappe_image_id';
  const META_MAPPE_DESCRIPTION = '_arkiv_mappe_description';
  const META_PDF_IDS = '_arkiv_pdf_ids';
  const OPTION_MAPPE_KNAPPER_ENABLED = 'arkiv_mappe_knapper_enabled';
  const OPTION_CPT_SLUG = 'arkiv_cpt_slug';
  const OPTION_TAX_SLUG = 'arkiv_tax_slug';
  const OPTION_BACK_PAGE_ID = 'arkiv_back_page_id';
  const OPTION_UPLOAD_REDIRECT_PAGE_ID = 'arkiv_upload_redirect_page_id';
  const OPTION_ADMIN_BAR_ROLES = 'arkiv_admin_bar_roles';
  const OPTION_LOGOUT_REDIRECT_SLUG = 'arkiv_logout_redirect_slug';
  const CAP_ADMIN_MENU = 'arkiv_admin_menu_access';

  private $mappe_settings_page_hook = '';

  public function __construct() {
    add_shortcode('arkiv_submit', [$this, 'render_shortcode']);
    add_shortcode('mappe_knapper', [$this, 'render_mappe_knapper_shortcode']);
    add_shortcode('logout_page', [$this, 'render_logout_shortcode']);
    add_action('init', [$this, 'maybe_handle_submit']);
    add_action('init', [$this, 'maybe_handle_edit']);
    add_action('add_meta_boxes', [$this, 'add_suggested_folder_metabox']);
    add_action('admin_menu', [$this, 'register_settings_page']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('admin_init', [$this, 'seed_admin_capabilities']);
    add_action('admin_init', [$this, 'maybe_handle_mappe_settings_save']);
    add_action('admin_init', [$this, 'maybe_handle_mappe_manager_actions']);
    add_action('admin_init', [$this, 'maybe_handle_admin_review']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_mappe_admin_assets']);
    add_action('wp_head', [$this, 'output_mappe_knapper_styles']);
    add_action('widgets_init', [$this, 'register_picture_of_day_widget']);
    add_action('wp_ajax_arkiv_submit', [$this, 'handle_ajax_submit']);
    add_action('wp_ajax_arkiv_create_post', [$this, 'handle_ajax_create_post']);
    add_action('wp_ajax_arkiv_upload_image', [$this, 'handle_ajax_upload_image']);
    add_action('wp_ajax_arkiv_upload_pdf', [$this, 'handle_ajax_upload_pdf']);
    add_filter('template_include', [$this, 'use_mappe_template'], 99);
    add_action('pre_get_posts', [$this, 'restrict_mappe_query_to_arkiv']);
    add_action('pre_comment_on_post', [$this, 'block_anonymous_comments']);
    add_filter('show_admin_bar', [$this, 'maybe_hide_admin_bar']);
    add_filter('private_title_format', fn() => '%s');
    add_filter('bbp_get_private_title_format', fn() => '%s');
    add_action('before_delete_post', [$this, 'delete_post_images_on_admin_delete']);
    add_action('wp_trash_post', [$this, 'delete_post_images_on_admin_delete']);
  }

  public static function activate() {
    if (get_option(self::OPTION_ADMIN_BAR_ROLES, null) === null) {
      update_option(self::OPTION_ADMIN_BAR_ROLES, ['administrator']);
    }

    self::seed_admin_capabilities_for_administrators();
  }

  public function seed_admin_capabilities() {
    self::seed_admin_capabilities_for_administrators();
  }

  public function register_picture_of_day_widget() {
    register_widget('Arkiv_Picture_Of_Day_Widget');
  }

  private static function seed_admin_capabilities_for_administrators() {
    $role = get_role('administrator');
    if ($role && !$role->has_cap(self::CAP_ADMIN_MENU)) {
      $role->add_cap(self::CAP_ADMIN_MENU);
    }
  }

  public function render_shortcode($atts) {
    if (!is_user_logged_in()) {
      return '<p>Du skal være logget ind for at indsende.</p>';
    }

    $terms = get_terms([
      'taxonomy' => $this->get_taxonomy_slug(),
      'hide_empty' => false,
    ]);

    $msg = '';
    if (!empty($_GET['arkiv_submit'])) {
      if ($_GET['arkiv_submit'] === 'ok') {
        $msg = '<div style="padding:10px;border:1px solid #46b450;background:#f0fff4;margin-bottom:12px;">Tak! Din historie er sendt til godkendelse.</div>';
      } elseif ($_GET['arkiv_submit'] === 'err') {
        $msg = '<div style="padding:10px;border:1px solid #dc3232;background:#fff0f0;margin-bottom:12px;">Noget gik galt. Prøv igen.</div>';
      }
    }

    ob_start();
    echo $msg;
    wp_enqueue_editor();
    ?>
    <form class="arkiv-submit-form" method="post" enctype="multipart/form-data" style="max-width:700px;">
      <?php wp_nonce_field('arkiv_submit_action', 'arkiv_submit_nonce'); ?>

      <p>
        <label><strong>Titel</strong></label><br>
        <input type="text" name="arkiv_title" required style="width:100%;padding:8px;">
      </p>

      <p>
        <label><strong>Historie</strong></label><br>
        <?php
        wp_editor('', 'arkiv_content_editor', [
          'textarea_name' => 'arkiv_content',
          'media_buttons' => false,
          'teeny' => true,
          'editor_height' => 240,
        ]);
        ?>
      </p>

      <p>
        <label><strong>Vælg mappe</strong></label><br>
        <select name="arkiv_folder_term" style="width:100%;padding:8px;">
          <option value="">— Vælg —</option>
          <?php
          if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
              printf('<option value="%d">%s</option>', (int) $t->term_id, esc_html($t->name));
            }
          }
          ?>
        </select>
        <small>Du kan også foreslå en ny mappe nedenfor.</small>
      </p>

      <p>
        <label><strong>Foreslå ny mappe (valgfri)</strong></label><br>
        <input type="text" name="arkiv_suggested_folder" placeholder="Fx 'Skolen' eller '1970’erne'" style="width:100%;padding:8px;">
      </p>

      <p>
        <label><strong>Upload billeder (valgfri, flere tilladt)</strong></label><br>
        <input id="arkivUploadImages" type="file" name="arkiv_images[]" accept="image/*" multiple data-max-files="50">
        <br><small>Tip: Vælg gerne 1–50 billeder. Første billede bruges som forsidebillede.</small>
        <div class="arkiv-upload-preview" id="arkivUploadPreview"></div>
      </p>

      <p>
        <label><strong>Upload PDF-dokumenter (valgfri, flere tilladt)</strong></label><br>
        <input id="arkivUploadPdfs" type="file" name="arkiv_pdfs[]" accept="application/pdf" multiple data-max-files="20">
        <br><small>Tip: Vælg gerne 1–20 PDF'er. De vises som forsider.</small>
        <div class="arkiv-upload-preview arkiv-upload-preview--pdf" id="arkivUploadPdfPreview"></div>
      </p>

      <p>
        <label>
          <input type="checkbox" name="arkiv_comments_enabled" value="1" checked>
          <strong>Kan kommenteres</strong>
        </label>
      </p>

      <p class="arkiv-upload-status" id="arkivUploadStatus" aria-live="polite">
        <span class="arkiv-upload-status-text"></span>
        <span class="arkiv-upload-spinner" aria-hidden="true"></span>
      </p>

      <p>
        <button type="submit" name="arkiv_submit_btn" value="1" style="padding:10px 14px;">
          Send til godkendelse
        </button>
      </p>
    </form>
    <style>
      .arkiv-upload-preview {
        margin-top: 12px;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
      }

      .arkiv-upload-item {
        background: #f7f7f7;
        border-radius: 12px;
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,.05);
      }

      .arkiv-upload-thumb {
        width: 100%;
        border-radius: 10px;
        object-fit: cover;
        background: #fff;
      }

      .arkiv-upload-pdf {
        width: 100%;
        min-height: 140px;
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

      .arkiv-upload-preview--pdf .arkiv-upload-name {
        text-align: center;
      }

      .arkiv-upload-name {
        font-size: 12px;
        color: #333;
        word-break: break-word;
      }

      .arkiv-upload-bar {
        position: relative;
        height: 6px;
        border-radius: 999px;
        background: #e1e1e1;
        overflow: hidden;
      }

      .arkiv-upload-bar span {
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 0%;
        background: #111;
        transition: width 0.2s ease;
      }

      .arkiv-upload-bar.is-error span {
        background: #dc3232;
      }

      .arkiv-upload-state {
        font-size: 12px;
        color: #555;
      }

      .arkiv-upload-state.is-error {
        color: #dc3232;
      }

      .arkiv-upload-status {
        margin-top: 8px;
        font-size: 13px;
        color: #555;
        display: inline-flex;
        align-items: center;
        gap: 8px;
      }

      .arkiv-submit-form.is-uploading button[type="submit"] {
        opacity: 0.6;
        pointer-events: none;
      }

      .arkiv-upload-spinner {
        width: 14px;
        height: 14px;
        border: 2px solid rgba(17, 17, 17, 0.2);
        border-top-color: rgba(17, 17, 17, 0.7);
        border-radius: 50%;
        animation: arkiv-spin 0.8s linear infinite;
        display: none;
      }

      .arkiv-upload-status.is-busy .arkiv-upload-spinner {
        display: inline-block;
      }

      @keyframes arkiv-spin {
        to {
          transform: rotate(360deg);
        }
      }
    </style>
    <script>
      (function () {
        const form = document.querySelector('.arkiv-submit-form');
        const uploadInput = document.getElementById('arkivUploadImages');
        const pdfInput = document.getElementById('arkivUploadPdfs');
        const previewWrap = document.getElementById('arkivUploadPreview');
        const pdfPreviewWrap = document.getElementById('arkivUploadPdfPreview');
        const statusEl = document.getElementById('arkivUploadStatus');
        const statusText = statusEl ? statusEl.querySelector('.arkiv-upload-status-text') : null;
        if (!form || !uploadInput || !pdfInput || !previewWrap || !pdfPreviewWrap || !statusEl || !statusText) return;

        const maxImageFiles = parseInt(uploadInput.dataset.maxFiles, 10) || 50;
        const maxPdfFiles = parseInt(pdfInput.dataset.maxFiles, 10) || 20;
        let imageMeta = [];
        let pdfMeta = [];

        function setStatus(message, busy = false) {
          statusText.textContent = message || '';
          statusEl.classList.toggle('is-busy', Boolean(busy));
        }

        function isPdfFile(file) {
          if (!file) return false;
          if (file.type === 'application/pdf') return true;
          return file.name && file.name.toLowerCase().endsWith('.pdf');
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

        uploadInput.addEventListener('change', function () {
          const files = Array.from(uploadInput.files || []).filter(file => file.type.startsWith('image/'));
          imageMeta = buildPreview(files, { wrap: previewWrap, maxFiles: maxImageFiles, isPdf: false });
        });

        pdfInput.addEventListener('change', function () {
          const files = Array.from(pdfInput.files || []).filter(file => isPdfFile(file));
          pdfMeta = buildPreview(files, { wrap: pdfPreviewWrap, maxFiles: maxPdfFiles, isPdf: true });
        });

        form.addEventListener('submit', function (event) {
          if (!window.FormData || !window.XMLHttpRequest) {
            return;
          }

          const files = Array.from(uploadInput.files || []).filter(file => file.type.startsWith('image/'));
          const pdfFiles = Array.from(pdfInput.files || []).filter(file => isPdfFile(file));
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
          setStatus('Opretter opslag...', true);

          const formData = new FormData(form);
          formData.delete('arkiv_images[]');
          formData.delete('arkiv_pdfs[]');
          formData.append('action', 'arkiv_create_post');

          const xhr = new XMLHttpRequest();
          xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
          xhr.responseType = 'json';

          xhr.addEventListener('load', async function () {
            if (!(xhr.status >= 200 && xhr.status < 300)) {
              form.classList.remove('is-uploading');
              setStatus('Noget gik galt. Prøv igen.');
              return;
            }

            const response = xhr.response || {};
            if (!response.success || !response.data || !response.data.post_id) {
              form.classList.remove('is-uploading');
              setStatus('Noget gik galt. Prøv igen.');
              return;
            }

            const postId = response.data.post_id;
            const redirectUrl = response.data.redirect || window.location.href.split('?')[0];
            const nonce = form.querySelector('[name="arkiv_submit_nonce"]')?.value || '';

            let hadError = false;

            for (let i = 0; i < imageMeta.length; i++) {
              const meta = imageMeta[i];
              setItemState(meta, 'Uploader');
              setStatus(`Uploader billede ${i + 1} af ${imageMeta.length}...`);
              const ok = await uploadSingleFile(meta, postId, nonce, 'arkiv_upload_image', 'arkiv_single_image');
              if (!ok) {
                hadError = true;
              }
            }

            for (let i = 0; i < pdfMeta.length; i++) {
              const meta = pdfMeta[i];
              setItemState(meta, 'Uploader');
              setStatus(`Uploader PDF ${i + 1} af ${pdfMeta.length}...`);
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

            setStatus('Arbejder...', true);
            window.location.href = redirectUrl;
          });

          xhr.addEventListener('error', function () {
            form.classList.remove('is-uploading');
            setStatus('Noget gik galt. Prøv igen.');
          });

          xhr.send(formData);
        });
      })();
    </script>
    <?php
    return ob_get_clean();
  }

  public function render_mappe_knapper_shortcode($atts) {
    $enabled = (int) get_option(self::OPTION_MAPPE_KNAPPER_ENABLED, 1);
    if ($enabled !== 1) {
      return '';
    }

    $atts = shortcode_atts([
      'taxonomy' => $this->get_taxonomy_slug(),
      'show_empty' => true,
    ], $atts);

    $show_empty = filter_var($atts['show_empty'], FILTER_VALIDATE_BOOLEAN);

    $terms = get_terms([
      'taxonomy' => $atts['taxonomy'],
      'hide_empty' => !$show_empty,
      'orderby' => 'name',
      'order' => 'ASC',
    ]);

    if (is_wp_error($terms) || empty($terms)) {
      return '';
    }

    ob_start();
    $instance_id = uniqid('mappe-knapper-', true);
    printf('<div class="mappe-knapper-block" data-mappe-knapper="%s">', esc_attr($instance_id));
    ?>
    <div class="mappe-search">
      <label class="screen-reader-text" for="<?php echo esc_attr($instance_id); ?>-search">Søg i mapper</label>
      <input
        id="<?php echo esc_attr($instance_id); ?>-search"
        class="mappe-search__input"
        type="search"
        placeholder="Søg efter mappe"
        aria-describedby="<?php echo esc_attr($instance_id); ?>-search-help"
      >
      <div id="<?php echo esc_attr($instance_id); ?>-search-help" class="screen-reader-text">
        Resultaterne opdateres, mens du skriver.
      </div>
    </div>
    <div class="mappe-knapper">
    <?php

    foreach ($terms as $term) {
      $url = get_term_link($term);
      if (is_wp_error($url)) {
        continue;
      }

      $image_id = (int) get_term_meta($term->term_id, self::META_MAPPE_IMAGE_ID, true);
      $description = get_term_meta($term->term_id, self::META_MAPPE_DESCRIPTION, true);
      $description = is_string($description) ? trim($description) : '';

      $image_html = '';
      if ($image_id) {
        $image_html = wp_get_attachment_image($image_id, 'medium', false, [
          'class' => 'mappe-knap__image',
          'alt' => $term->name,
          'loading' => 'lazy',
          'title' => $description !== '' ? $description : null,
        ]);

        if ($description !== '') {
          $image_html = sprintf(
            '<span class="mappe-knap__image-wrap">%s<span class="mappe-knap__tooltip">%s</span></span>',
            $image_html,
            esc_html($description)
          );
        }
      }

      printf(
        '<a class="mappe-knap" href="%s" data-mappe-title="%s" data-mappe-desc="%s">%s<span class="mappe-knap__text"><span class="mappe-knap__title">%s</span>%s</span></a>',
        esc_url($url),
        esc_attr(wp_strip_all_tags($term->name)),
        esc_attr(wp_strip_all_tags($description)),
        $image_html,
        esc_html($term->name),
        $description !== '' ? '<span class="mappe-knap__desc">' . esc_html($description) . '</span>' : ''
      );
    }

    echo '</div>';
    ?>
    <script>
      (function () {
        const block = document.querySelector('[data-mappe-knapper="<?php echo esc_js($instance_id); ?>"]');
        if (!block) {
          return;
        }

        const searchInput = block.querySelector('.mappe-search__input');
        const container = block.querySelector('.mappe-knapper');
        if (!searchInput || !container) {
          return;
        }

        const items = Array.from(container.querySelectorAll('.mappe-knap'));
        const originalOrder = items.slice();
        const normalised = items.map((item) => ({
          element: item,
          title: (item.dataset.mappeTitle || '').toLowerCase(),
          desc: (item.dataset.mappeDesc || '').toLowerCase(),
        }));

        const applyFilter = () => {
          const query = searchInput.value.trim().toLowerCase();
          if (!query) {
            originalOrder.forEach((item) => {
              item.style.display = '';
              container.appendChild(item);
            });
            return;
          }

          const titleMatches = [];
          const descMatches = [];
          normalised.forEach((entry) => {
            if (entry.title.includes(query)) {
              titleMatches.push(entry.element);
            } else if (entry.desc.includes(query)) {
              descMatches.push(entry.element);
            } else {
              entry.element.style.display = 'none';
            }
          });

          const ordered = titleMatches.concat(descMatches);
          ordered.forEach((item) => {
            item.style.display = '';
            container.appendChild(item);
          });
        };

        searchInput.addEventListener('input', applyFilter);
      })();
    </script>
    </div>
    <?php
    return ob_get_clean();
  }

  public function output_mappe_knapper_styles() {
    ?>
    <style>
      .mappe-knapper {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
      }

      .mappe-search {
        margin: 16px 0 8px;
      }

      .mappe-search__input {
        width: 100%;
        max-width: 480px;
        padding: 10px 12px;
        border: 1px solid #d7d7d7;
        border-radius: 6px;
        font-size: 16px;
      }

      .mappe-knap {
        position: relative;
        display: flex;
        gap: 12px;
        align-items: center;
        padding: 10px 14px;
        background: #f7f7f7;
        border-radius: 16px;
        text-decoration: none;
        color: #333;
        font-size: 14px;
        transition: background 0.2s ease;
      }

      .mappe-knap:hover {
        background: #ddd;
      }

      .mappe-knap__image {
        width: 64px;
        height: 64px;
        object-fit: cover;
        border-radius: 12px;
        flex-shrink: 0;
      }

      .mappe-knap__image-wrap {
        display: inline-flex;
      }

      .mappe-knap__tooltip {
        position: absolute;
        left: calc(100% + 8px);
        top: 50%;
        transform: translateX(-50%);
        background: rgba(17, 17, 17, 0.9);
        color: #fff;
        padding: 6px 10px;
        border-radius: 8px;
        font-size: 12px;
        line-height: 1.3;
        max-width: 220px;
        white-space: normal;
        text-align: center;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.2s ease;
        z-index: 10;
      }

      .mappe-knap__image-wrap:hover .mappe-knap__tooltip,
      .mappe-knap:hover .mappe-knap__tooltip {
        opacity: 1;
        visibility: visible;
      }

      .mappe-knap__text {
        display: flex;
        flex-direction: column;
        gap: 4px;
      }

      .mappe-knap__title {
        font-weight: 600;
      }

      .mappe-knap__desc {
        font-size: 13px;
        color: #555;
        max-width: 20ch;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        overflow: hidden;
      }
    </style>
    <?php
  }

  public function block_anonymous_comments($comment_post_ID) {
    if (is_user_logged_in()) {
      return;
    }

    $post_type = get_post_type($comment_post_ID);
    if ($post_type !== self::CPT) {
      return;
    }

    wp_die(
      'Du skal være logget ind for at kommentere.',
      'Kommentar kræver login',
      [
        'response' => 403,
        'back_link' => true,
      ]
    );
  }

  public function register_settings_page() {
    add_menu_page(
      'Arkiv Submission',
      'Arkiv Submission',
      self::CAP_ADMIN_MENU,
      'arkiv-submission-settings',
      [$this, 'render_settings_page'],
      'dashicons-archive',
      80
    );

    add_submenu_page(
      'arkiv-submission-settings',
      'Mapper',
      'Mapper',
      self::CAP_ADMIN_MENU,
      'arkiv-mapper',
      [$this, 'render_mappe_manager_page']
    );

    $this->mappe_settings_page_hook = add_submenu_page(
      'arkiv-submission-settings',
      'Mappe knapper',
      'Mappe knapper',
      self::CAP_ADMIN_MENU,
      'arkiv-mappe-settings',
      [$this, 'render_mappe_settings_page']
    );

    add_submenu_page(
      'arkiv-submission-settings',
      'Admin bar',
      'Admin bar',
      'manage_options',
      'arkiv-admin-bar-settings',
      [$this, 'render_admin_bar_settings_page']
    );

    add_submenu_page(
      'arkiv-submission-settings',
      'Afventer godkendelse',
      'Afventer godkendelse',
      self::CAP_ADMIN_MENU,
      'arkiv-pending-posts',
      [$this, 'render_pending_posts_page']
    );
  }

  public function register_settings() {
    register_setting(
      'arkiv_submission_settings',
      self::OPTION_MAPPE_KNAPPER_ENABLED,
      [
        'type' => 'boolean',
        'sanitize_callback' => [$this, 'sanitize_checkbox'],
        'default' => 1,
      ]
    );

    register_setting(
      'arkiv_submission_settings',
      self::OPTION_CPT_SLUG,
      [
        'type' => 'string',
        'sanitize_callback' => [$this, 'sanitize_cpt_slug'],
        'default' => self::CPT,
      ]
    );

    register_setting(
      'arkiv_submission_settings',
      self::OPTION_TAX_SLUG,
      [
        'type' => 'string',
        'sanitize_callback' => [$this, 'sanitize_tax_slug'],
        'default' => self::TAX,
      ]
    );

    register_setting(
      'arkiv_submission_settings',
      self::OPTION_BACK_PAGE_ID,
      [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 0,
      ]
    );

    register_setting(
      'arkiv_submission_settings',
      self::OPTION_UPLOAD_REDIRECT_PAGE_ID,
      [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 0,
      ]
    );

    register_setting(
      'arkiv_submission_settings',
      self::OPTION_LOGOUT_REDIRECT_SLUG,
      [
        'type' => 'string',
        'sanitize_callback' => [$this, 'sanitize_logout_slug'],
        'default' => 'login',
      ]
    );

    register_setting(
      'arkiv_submission_admin_bar_settings',
      self::OPTION_ADMIN_BAR_ROLES,
      [
        'type' => 'array',
        'sanitize_callback' => [$this, 'sanitize_admin_bar_roles'],
        'default' => ['administrator'],
      ]
    );
  }

  public function sanitize_checkbox($value) {
    return !empty($value) ? 1 : 0;
  }

  public function sanitize_cpt_slug($value) {
    $value = sanitize_key($value);
    return $value !== '' ? $value : self::CPT;
  }

  public function sanitize_tax_slug($value) {
    $value = sanitize_key($value);
    return $value !== '' ? $value : self::TAX;
  }

  public function sanitize_admin_bar_roles($value) {
    if (!is_array($value)) {
      return [];
    }

    $roles = array_keys(get_editable_roles());
    $value = array_map('sanitize_key', $value);
    return array_values(array_intersect($roles, $value));
  }

  public function sanitize_logout_slug($value) {
    $value = sanitize_title($value);
    return $value !== '' ? $value : 'login';
  }

  public function maybe_hide_admin_bar($show) {
    if (!is_user_logged_in()) {
      return $show;
    }

    $allowed_roles = $this->get_allowed_admin_bar_roles();
    if (empty($allowed_roles)) {
      return false;
    }

    $user = wp_get_current_user();
    if (empty($user->roles)) {
      return $show;
    }

    foreach ($user->roles as $role) {
      if (in_array($role, $allowed_roles, true)) {
        return $show;
      }
    }

    return false;
  }

  public function render_settings_page() {
    if (!current_user_can('manage_options')) {
      if (current_user_can(self::CAP_ADMIN_MENU)) {
        $url = menu_page_url('arkiv-mapper', false);
        if ($url) {
          wp_safe_redirect($url);
          exit;
        }
      }
      return;
    }

    $enabled = (int) get_option(self::OPTION_MAPPE_KNAPPER_ENABLED, 1);
    $cpt_slug = $this->get_post_type_slug();
    $tax_slug = $this->get_taxonomy_slug();
    $back_page_id = (int) get_option(self::OPTION_BACK_PAGE_ID, 0);
    $upload_redirect_page_id = (int) get_option(self::OPTION_UPLOAD_REDIRECT_PAGE_ID, 0);
    $logout_redirect_slug = $this->get_logout_redirect_slug();
    ?>
    <div class="wrap">
      <h1>Arkiv Submission</h1>
      <form method="post" action="options.php">
        <?php settings_fields('arkiv_submission_settings'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">Post type slug</th>
            <td>
              <input type="text" name="<?php echo esc_attr(self::OPTION_CPT_SLUG); ?>" value="<?php echo esc_attr($cpt_slug); ?>" class="regular-text">
              <p class="description">Skal matche navnet på din registrerede post type.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Taxonomy slug</th>
            <td>
              <input type="text" name="<?php echo esc_attr(self::OPTION_TAX_SLUG); ?>" value="<?php echo esc_attr($tax_slug); ?>" class="regular-text">
              <p class="description">Skal matche navnet på din registrerede taxonomy.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Mappe knapper shortcode</th>
            <td>
              <label>
                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_MAPPE_KNAPPER_ENABLED); ?>" value="1" <?php checked(1, $enabled); ?>>
                Aktivér [mappe_knapper] shortcode
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row">Tilbage-link side</th>
            <td>
              <?php
              wp_dropdown_pages([
                'name' => self::OPTION_BACK_PAGE_ID,
                'selected' => $back_page_id,
                'show_option_none' => '— Vælg side —',
              ]);
              ?>
              <p class="description">Vælg siden som "Tilbage til arkivet" skal pege på.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Upload færdig side</th>
            <td>
              <?php
              wp_dropdown_pages([
                'name' => self::OPTION_UPLOAD_REDIRECT_PAGE_ID,
                'selected' => $upload_redirect_page_id,
                'show_option_none' => '— Vælg side —',
              ]);
              ?>
              <p class="description">Vælg siden brugerne sendes til når upload er færdig.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Logout redirect slug</th>
            <td>
              <input type="text" name="<?php echo esc_attr(self::OPTION_LOGOUT_REDIRECT_SLUG); ?>" value="<?php echo esc_attr($logout_redirect_slug); ?>" class="regular-text">
              <p class="description">Sluggen for siden brugeren sendes til efter logout (fx "login").</p>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  public function render_mappe_settings_page() {
    if (!current_user_can(self::CAP_ADMIN_MENU)) {
      return;
    }

    $terms = get_terms([
      'taxonomy' => $this->get_taxonomy_slug(),
      'hide_empty' => false,
    ]);
    ?>
    <div class="wrap">
      <h1>Mappe knapper</h1>
      <p>Tilføj billede og beskrivelse til mapperne, som vises i [mappe_knapper].</p>
      <form method="post" action="">
        <?php wp_nonce_field('arkiv_mappe_settings_save', 'arkiv_mappe_settings_nonce'); ?>
        <input type="hidden" name="arkiv_mappe_settings_submit" value="1">
        <table class="widefat striped">
          <thead>
            <tr>
              <th scope="col">Mappe</th>
              <th scope="col">Billede</th>
              <th scope="col">Beskrivelse</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!is_wp_error($terms) && !empty($terms)) : ?>
              <?php foreach ($terms as $term) : ?>
                <?php
                $image_id = (int) get_term_meta($term->term_id, self::META_MAPPE_IMAGE_ID, true);
                $description = get_term_meta($term->term_id, self::META_MAPPE_DESCRIPTION, true);
                $description = is_string($description) ? $description : '';
                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
                ?>
                <tr>
                  <td>
                    <strong><?php echo esc_html($term->name); ?></strong>
                    <input type="hidden" name="mappe_term_ids[]" value="<?php echo (int) $term->term_id; ?>">
                  </td>
                  <td>
                    <div class="arkiv-mappe-image">
                      <img src="<?php echo esc_url($image_url); ?>" alt="" style="<?php echo $image_url ? '' : 'display:none;'; ?>max-width:120px;height:auto;border-radius:8px;">
                      <input type="hidden" class="mappe-image-id" name="mappe_image_id[<?php echo (int) $term->term_id; ?>]" value="<?php echo $image_id ? (int) $image_id : ''; ?>">
                      <div style="margin-top:8px;">
                        <button type="button" class="button mappe-image-select">Vælg billede</button>
                        <button type="button" class="button mappe-image-remove" <?php echo $image_url ? '' : 'style="display:none;"'; ?>>Fjern</button>
                      </div>
                    </div>
                  </td>
                  <td>
                    <textarea name="mappe_description[<?php echo (int) $term->term_id; ?>]" rows="3" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else : ?>
              <tr>
                <td colspan="3">Ingen mapper fundet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?php submit_button('Gem ændringer'); ?>
      </form>
    </div>
    <?php
  }

  public function render_mappe_manager_page() {
    if (!current_user_can(self::CAP_ADMIN_MENU)) {
      return;
    }

    $taxonomy = $this->get_taxonomy_slug();
    $terms = get_terms([
      'taxonomy' => $taxonomy,
      'hide_empty' => false,
    ]);

    $status = isset($_GET['arkiv_mappe_status']) ? sanitize_key($_GET['arkiv_mappe_status']) : '';
    $message = '';
    $message_class = 'notice-success';
    if ($status === 'created') {
      $message = 'Ny mappe er oprettet.';
    } elseif ($status === 'deleted') {
      $message = 'Mappen er slettet.';
    } elseif ($status === 'error') {
      $message = 'Noget gik galt. Prøv igen.';
      $message_class = 'notice-error';
    }
    ?>
    <div class="wrap">
      <h1>Mapper</h1>
      <p>Opret og slet mapper som bruges til Arkiv-indlæg.</p>
      <?php if ($message !== '') : ?>
        <div class="notice <?php echo esc_attr($message_class); ?> is-dismissible">
          <p><?php echo esc_html($message); ?></p>
        </div>
      <?php endif; ?>

      <h2>Opret ny mappe</h2>
      <form method="post" action="">
        <?php wp_nonce_field('arkiv_mappe_create', 'arkiv_mappe_create_nonce'); ?>
        <input type="hidden" name="arkiv_mappe_create_submit" value="1">
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="arkivMappeName">Navn</label></th>
            <td>
              <input id="arkivMappeName" name="arkiv_mappe_name" type="text" class="regular-text" required>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="arkivMappeDescription">Beskrivelse</label></th>
            <td>
              <textarea id="arkivMappeDescription" name="arkiv_mappe_description" rows="3" class="large-text"></textarea>
            </td>
          </tr>
        </table>
        <?php submit_button('Opret mappe'); ?>
      </form>

      <h2>Eksisterende mapper</h2>
      <table class="widefat striped">
        <thead>
          <tr>
            <th scope="col">Mappe</th>
            <th scope="col">Slug</th>
            <th scope="col">Antal indlæg</th>
            <th scope="col">Handling</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!is_wp_error($terms) && !empty($terms)) : ?>
            <?php foreach ($terms as $term) : ?>
              <tr>
                <td>
                  <strong><?php echo esc_html($term->name); ?></strong>
                </td>
                <td><?php echo esc_html($term->slug); ?></td>
                <td><?php echo (int) $term->count; ?></td>
                <td>
                  <form method="post" action="" class="arkiv-mappe-delete-form" style="display:inline;">
                    <?php wp_nonce_field('arkiv_mappe_delete', 'arkiv_mappe_delete_nonce'); ?>
                    <input type="hidden" name="arkiv_mappe_delete_submit" value="1">
                    <input type="hidden" name="arkiv_mappe_delete_term" value="<?php echo (int) $term->term_id; ?>">
                    <button type="submit" class="button button-link-delete arkiv-mappe-delete-button">Slet</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr>
              <td colspan="4">Ingen mapper fundet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <script>
      (function () {
        const deleteButtons = document.querySelectorAll('.arkiv-mappe-delete-button');
        if (!deleteButtons.length) {
          return;
        }
        deleteButtons.forEach(button => {
          button.addEventListener('click', function (event) {
            const confirmed = window.confirm('Vil du virkelig slette denne mappe?');
            if (!confirmed) {
              event.preventDefault();
            }
          });
        });
      })();
    </script>
    <?php
  }

  public function maybe_handle_mappe_manager_actions() {
    if (!current_user_can(self::CAP_ADMIN_MENU)) {
      return;
    }

    $taxonomy = $this->get_taxonomy_slug();

    if (!empty($_POST['arkiv_mappe_create_submit'])) {
      if (empty($_POST['arkiv_mappe_create_nonce']) || !wp_verify_nonce($_POST['arkiv_mappe_create_nonce'], 'arkiv_mappe_create')) {
        return;
      }

      $name = isset($_POST['arkiv_mappe_name']) ? sanitize_text_field(wp_unslash($_POST['arkiv_mappe_name'])) : '';
      $description = isset($_POST['arkiv_mappe_description']) ? sanitize_textarea_field(wp_unslash($_POST['arkiv_mappe_description'])) : '';

      if ($name === '') {
        $this->redirect_mappe_manager('error');
      }

      $result = wp_insert_term($name, $taxonomy, [
        'description' => $description,
      ]);

      if (is_wp_error($result)) {
        $this->redirect_mappe_manager('error');
      }

      $this->redirect_mappe_manager('created');
    }

    if (!empty($_POST['arkiv_mappe_delete_submit'])) {
      if (empty($_POST['arkiv_mappe_delete_nonce']) || !wp_verify_nonce($_POST['arkiv_mappe_delete_nonce'], 'arkiv_mappe_delete')) {
        return;
      }

      $term_id = isset($_POST['arkiv_mappe_delete_term']) ? absint($_POST['arkiv_mappe_delete_term']) : 0;
      if ($term_id > 0) {
        $this->reassign_mappe_content_to_uncategorized($term_id, $taxonomy);
        $result = wp_delete_term($term_id, $taxonomy);
        if (is_wp_error($result)) {
          $this->redirect_mappe_manager('error');
        }
      }

      $this->redirect_mappe_manager('deleted');
    }
  }

  private function redirect_mappe_manager($status) {
    $url = menu_page_url('arkiv-mapper', false);
    if (!$url) {
      return;
    }
    wp_safe_redirect(add_query_arg('arkiv_mappe_status', $status, $url));
    exit;
  }

  private function reassign_mappe_content_to_uncategorized($term_id, $taxonomy) {
    $uncategorized_term = $this->get_or_create_uncategorized_term($taxonomy);
    if (!$uncategorized_term || is_wp_error($uncategorized_term)) {
      return;
    }

    if ((int) $uncategorized_term->term_id === (int) $term_id) {
      return;
    }

    $object_ids = get_objects_in_term($term_id, $taxonomy);
    if (is_wp_error($object_ids) || empty($object_ids)) {
      return;
    }

    foreach ($object_ids as $object_id) {
      wp_set_object_terms((int) $object_id, [(int) $uncategorized_term->term_id], $taxonomy, false);
    }
  }

  private function get_or_create_uncategorized_term($taxonomy) {
    $uncategorized = get_term_by('name', 'Ukategoriserede', $taxonomy);
    if ($uncategorized && !is_wp_error($uncategorized)) {
      return $uncategorized;
    }

    $result = wp_insert_term('Ukategoriserede', $taxonomy);
    if (is_wp_error($result)) {
      return $result;
    }

    return get_term((int) $result['term_id'], $taxonomy);
  }

  public function render_admin_bar_settings_page() {
    if (!current_user_can('manage_options')) {
      return;
    }

    $roles = get_editable_roles();
    $allowed_roles = $this->get_allowed_admin_bar_roles();
    ?>
    <div class="wrap">
      <h1>Admin bar</h1>
      <p>Vælg hvilke roller der må se den øverste WordPress værktøjslinje.</p>
      <form method="post" action="options.php">
        <?php settings_fields('arkiv_submission_admin_bar_settings'); ?>
        <table class="form-table" role="presentation">
          <tbody>
            <?php foreach ($roles as $role_key => $role_data) : ?>
              <tr>
                <th scope="row"><?php echo esc_html($role_data['name']); ?></th>
                <td>
                  <label>
                    <input
                      type="checkbox"
                      name="<?php echo esc_attr(self::OPTION_ADMIN_BAR_ROLES); ?>[]"
                      value="<?php echo esc_attr($role_key); ?>"
                      <?php checked(in_array($role_key, $allowed_roles, true)); ?>
                      aria-label="Må se admin bar"
                    >
                  </label>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  public function maybe_handle_mappe_settings_save() {
    if (empty($_POST['arkiv_mappe_settings_submit'])) {
      return;
    }

    if (!current_user_can(self::CAP_ADMIN_MENU)) {
      return;
    }

    if (empty($_POST['arkiv_mappe_settings_nonce']) || !wp_verify_nonce($_POST['arkiv_mappe_settings_nonce'], 'arkiv_mappe_settings_save')) {
      return;
    }

    $term_ids = isset($_POST['mappe_term_ids']) && is_array($_POST['mappe_term_ids']) ? array_map('absint', $_POST['mappe_term_ids']) : [];
    $image_ids = isset($_POST['mappe_image_id']) && is_array($_POST['mappe_image_id']) ? $_POST['mappe_image_id'] : [];
    $descriptions = isset($_POST['mappe_description']) && is_array($_POST['mappe_description']) ? $_POST['mappe_description'] : [];

    foreach ($term_ids as $term_id) {
      $image_id = isset($image_ids[$term_id]) ? absint($image_ids[$term_id]) : 0;
      $description = isset($descriptions[$term_id]) ? sanitize_textarea_field($descriptions[$term_id]) : '';

      if ($image_id) {
        update_term_meta($term_id, self::META_MAPPE_IMAGE_ID, $image_id);
      } else {
        delete_term_meta($term_id, self::META_MAPPE_IMAGE_ID);
      }

      if ($description !== '') {
        update_term_meta($term_id, self::META_MAPPE_DESCRIPTION, $description);
      } else {
        delete_term_meta($term_id, self::META_MAPPE_DESCRIPTION);
      }
    }
  }

  public function enqueue_mappe_admin_assets($hook) {
    if (!$this->mappe_settings_page_hook || $hook !== $this->mappe_settings_page_hook) {
      return;
    }

    wp_enqueue_media();
    wp_add_inline_script('jquery', $this->get_mappe_admin_script());
  }

  private function get_mappe_admin_script() {
    return <<<JS
    jQuery(function ($) {
      $('.mappe-image-select').on('click', function (e) {
        e.preventDefault();
        const container = $(this).closest('.arkiv-mappe-image');
        const imageField = container.find('.mappe-image-id');
        const imagePreview = container.find('img');
        const removeButton = container.find('.mappe-image-remove');
        const frame = wp.media({
          title: 'Vælg billede',
          button: { text: 'Brug billede' },
          multiple: false
        });

        frame.on('select', function () {
          const attachment = frame.state().get('selection').first().toJSON();
          imageField.val(attachment.id);
          if (attachment.sizes && attachment.sizes.medium) {
            imagePreview.attr('src', attachment.sizes.medium.url);
          } else {
            imagePreview.attr('src', attachment.url);
          }
          imagePreview.show();
          removeButton.show();
        });

        frame.open();
      });

      $('.mappe-image-remove').on('click', function (e) {
        e.preventDefault();
        const container = $(this).closest('.arkiv-mappe-image');
        container.find('.mappe-image-id').val('');
        container.find('img').hide().attr('src', '');
        $(this).hide();
      });
    });
JS;
  }

  private function get_allowed_admin_bar_roles() {
    $saved_roles = get_option(self::OPTION_ADMIN_BAR_ROLES, null);
    if ($saved_roles === null) {
      return array_keys(get_editable_roles());
    }

    if (!is_array($saved_roles)) {
      return [];
    }

    return $saved_roles;
  }

  public function maybe_handle_submit() {
    if (empty($_POST['arkiv_submit_btn'])) {
      return;
    }
    if (!is_user_logged_in()) {
      $this->redirect_with('err');
    }

    if (empty($_POST['arkiv_submit_nonce']) || !wp_verify_nonce($_POST['arkiv_submit_nonce'], 'arkiv_submit_action')) {
      $this->redirect_with('err');
    }
    $result = $this->submit_post();
    if (is_wp_error($result)) {
      $this->redirect_with('err');
    }
    $this->redirect_with('ok');
  }

  public function handle_ajax_submit() {
    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => 'Ikke logget ind.'], 403);
    }

    if (empty($_POST['arkiv_submit_nonce']) || !wp_verify_nonce($_POST['arkiv_submit_nonce'], 'arkiv_submit_action')) {
      wp_send_json_error(['message' => 'Ugyldig anmodning.'], 400);
    }

    $result = $this->submit_post();
    if (is_wp_error($result)) {
      wp_send_json_error(['message' => 'Noget gik galt.'], 400);
    }

    $redirect = $this->get_success_redirect_url($this->get_fallback_redirect_url());
    wp_send_json_success(['redirect' => $redirect]);
  }

  public function handle_ajax_create_post() {
    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => 'Ikke logget ind.'], 403);
    }

    if (empty($_POST['arkiv_submit_nonce']) || !wp_verify_nonce($_POST['arkiv_submit_nonce'], 'arkiv_submit_action')) {
      wp_send_json_error(['message' => 'Ugyldig anmodning.'], 400);
    }

    $result = $this->submit_post(false);
    if (is_wp_error($result)) {
      wp_send_json_error(['message' => 'Noget gik galt.'], 400);
    }

    $redirect = $this->get_success_redirect_url($this->get_fallback_redirect_url());
    wp_send_json_success([
      'post_id' => $result,
      'redirect' => $redirect,
    ]);
  }

  public function handle_ajax_upload_image() {
    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => 'Ikke logget ind.'], 403);
    }

    if (empty($_POST['arkiv_submit_nonce']) || !wp_verify_nonce($_POST['arkiv_submit_nonce'], 'arkiv_submit_action')) {
      wp_send_json_error(['message' => 'Ugyldig anmodning.'], 400);
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if (!$post_id) {
      wp_send_json_error(['message' => 'Ugyldigt opslag.'], 400);
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== $this->get_post_type_slug()) {
      wp_send_json_error(['message' => 'Ugyldigt opslag.'], 400);
    }

    if ((int) $post->post_author !== get_current_user_id()) {
      wp_send_json_error(['message' => 'Ingen adgang.'], 403);
    }

    $attachment_id = $this->handle_single_image_upload($post_id);
    if (is_wp_error($attachment_id) || !$attachment_id) {
      wp_send_json_error(['message' => 'Upload fejlede.'], 400);
    }

    wp_send_json_success(['attachment_id' => $attachment_id]);
  }

  public function handle_ajax_upload_pdf() {
    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => 'Ikke logget ind.'], 403);
    }

    if (empty($_POST['arkiv_submit_nonce']) || !wp_verify_nonce($_POST['arkiv_submit_nonce'], 'arkiv_submit_action')) {
      wp_send_json_error(['message' => 'Ugyldig anmodning.'], 400);
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if (!$post_id) {
      wp_send_json_error(['message' => 'Ugyldigt opslag.'], 400);
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== $this->get_post_type_slug()) {
      wp_send_json_error(['message' => 'Ugyldigt opslag.'], 400);
    }

    if ((int) $post->post_author !== get_current_user_id()) {
      wp_send_json_error(['message' => 'Ingen adgang.'], 403);
    }

    $attachment_id = $this->handle_single_pdf_upload($post_id);
    if (is_wp_error($attachment_id) || !$attachment_id) {
      wp_send_json_error(['message' => 'Upload fejlede.'], 400);
    }

    wp_send_json_success(['attachment_id' => $attachment_id]);
  }

  private function submit_post($include_images = true) {
    if (!is_user_logged_in()) {
      return new WP_Error('arkiv_not_logged_in', 'Not logged in');
    }

    $title = isset($_POST['arkiv_title']) ? sanitize_text_field($_POST['arkiv_title']) : '';
    $content = isset($_POST['arkiv_content']) ? wp_kses_post($_POST['arkiv_content']) : '';
    $term_id = isset($_POST['arkiv_folder_term']) ? (int) $_POST['arkiv_folder_term'] : 0;
    $suggest = isset($_POST['arkiv_suggested_folder']) ? sanitize_text_field($_POST['arkiv_suggested_folder']) : '';
    $comments_enabled = !empty($_POST['arkiv_comments_enabled']);

    if (trim($title) === '' || trim(wp_strip_all_tags($content)) === '') {
      return new WP_Error('arkiv_missing_fields', 'Missing fields');
    }

    $post_id = wp_insert_post([
      'post_type' => $this->get_post_type_slug(),
      'post_title' => $title,
      'post_content' => $content,
      'comment_status' => $comments_enabled ? 'open' : 'closed',
      'post_status' => 'pending',
      'post_author' => get_current_user_id(),
    ], true);

    if (is_wp_error($post_id) || !$post_id) {
      return new WP_Error('arkiv_insert_failed', 'Insert failed');
    }

    if ($suggest !== '') {
      update_post_meta($post_id, self::META_SUGGESTED_FOLDER, $suggest);
    }

    if ($term_id > 0) {
      wp_set_object_terms($post_id, [$term_id], $this->get_taxonomy_slug(), false);
    }

    $attachment_ids = [];
    if ($include_images) {
      $attachment_ids = $this->handle_images_upload($post_id);

      if (!empty($attachment_ids)) {
        set_post_thumbnail($post_id, $attachment_ids[0]);
        update_post_meta($post_id, '_arkiv_gallery_ids', $attachment_ids);
      }
    }

    $pdf_ids = $this->handle_pdfs_upload($post_id);
    if (!empty($pdf_ids)) {
      update_post_meta($post_id, self::META_PDF_IDS, $pdf_ids);
      if (empty($attachment_ids) && !has_post_thumbnail($post_id)) {
        set_post_thumbnail($post_id, $pdf_ids[0]);
      }
    }

    return $post_id;
  }

  public function add_suggested_folder_metabox() {
    add_meta_box(
      'arkiv_suggested_folder_box',
      'Foreslået mappe',
      [$this, 'render_suggested_folder_metabox'],
      $this->get_post_type_slug(),
      'side',
      'high'
    );
  }

  public function render_suggested_folder_metabox($post) {
    $suggested = get_post_meta($post->ID, self::META_SUGGESTED_FOLDER, true);

    if (!$suggested) {
      echo '<p><em>Ingen foreslået mappe.</em></p>';
      return;
    }

    echo '<p><strong>Bruger foreslog:</strong></p>';
    echo '<p style="margin:0; padding:8px; background:#f6f7f7; border:1px solid #dcdcde;">'
      . esc_html($suggested)
      . '</p>';
    echo '<p style="margin-top:8px;"><small>Du kan vælge/rette den endelige mappe i boksen “Mappe”.</small></p>';
  }

  private function handle_images_upload($post_id) {
    if (empty($_FILES['arkiv_images']) || empty($_FILES['arkiv_images']['name'])) {
      return [];
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $ids = [];

    $files = $_FILES['arkiv_images'];
    $count = is_array($files['name']) ? count($files['name']) : 0;

    // Begræns antal billeder (kan justeres)
    $max = 50;
    $count = min($count, $max);

    for ($i = 0; $i < $count; $i++) {
      if (empty($files['name'][$i])) {
        continue;
      }

      // Byg en "single file" struktur WordPress forventer
      $file = [
        'name' => $files['name'][$i],
        'type' => $files['type'][$i],
        'tmp_name' => $files['tmp_name'][$i],
        'error' => $files['error'][$i],
        'size' => $files['size'][$i],
      ];

      // Basic filtype check (kun billeder)
      $ft = wp_check_filetype($file['name']);
      $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      if (empty($ft['ext']) || !in_array(strtolower($ft['ext']), $allowed, true)) {
        continue;
      }

      // Trick: midlertidigt erstat $_FILES for media_handle_upload
      $_FILES['arkiv_single_image'] = $file;

      $att_id = media_handle_upload('arkiv_single_image', $post_id);
      if (!is_wp_error($att_id) && $att_id) {
        $ids[] = (int) $att_id;
      }
    }

    return $ids;
  }

  private function handle_single_image_upload($post_id) {
    if (empty($_FILES['arkiv_single_image'])) {
      return new WP_Error('arkiv_missing_file', 'Missing file');
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file = $_FILES['arkiv_single_image'];

    $ft = wp_check_filetype($file['name']);
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (empty($ft['ext']) || !in_array(strtolower($ft['ext']), $allowed, true)) {
      return new WP_Error('arkiv_invalid_file', 'Invalid file');
    }

    $attachment_id = media_handle_upload('arkiv_single_image', $post_id);
    if (is_wp_error($attachment_id) || !$attachment_id) {
      return new WP_Error('arkiv_upload_failed', 'Upload failed');
    }

    $gallery_ids = get_post_meta($post_id, '_arkiv_gallery_ids', true);
    if (!is_array($gallery_ids)) {
      $gallery_ids = [];
    }
    $gallery_ids[] = (int) $attachment_id;
    update_post_meta($post_id, '_arkiv_gallery_ids', $gallery_ids);

    if (!has_post_thumbnail($post_id)) {
      set_post_thumbnail($post_id, $attachment_id);
    }

    return (int) $attachment_id;
  }

  private function handle_pdfs_upload($post_id) {
    if (empty($_FILES['arkiv_pdfs']) || empty($_FILES['arkiv_pdfs']['name'])) {
      return [];
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $ids = [];
    $files = $_FILES['arkiv_pdfs'];
    $count = is_array($files['name']) ? count($files['name']) : 0;

    $max = 20;
    $count = min($count, $max);

    for ($i = 0; $i < $count; $i++) {
      if (empty($files['name'][$i])) {
        continue;
      }

      $file = [
        'name' => $files['name'][$i],
        'type' => $files['type'][$i],
        'tmp_name' => $files['tmp_name'][$i],
        'error' => $files['error'][$i],
        'size' => $files['size'][$i],
      ];

      $ft = wp_check_filetype($file['name']);
      if (empty($ft['ext']) || strtolower($ft['ext']) !== 'pdf') {
        continue;
      }

      $_FILES['arkiv_single_pdf'] = $file;

      $att_id = media_handle_upload('arkiv_single_pdf', $post_id);
      if (!is_wp_error($att_id) && $att_id) {
        $ids[] = (int) $att_id;
      }
    }

    return $ids;
  }

  private function handle_single_pdf_upload($post_id) {
    if (empty($_FILES['arkiv_single_pdf'])) {
      return new WP_Error('arkiv_missing_file', 'Missing file');
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file = $_FILES['arkiv_single_pdf'];

    $ft = wp_check_filetype($file['name']);
    if (empty($ft['ext']) || strtolower($ft['ext']) !== 'pdf') {
      return new WP_Error('arkiv_invalid_file', 'Invalid file');
    }

    $attachment_id = media_handle_upload('arkiv_single_pdf', $post_id);
    if (is_wp_error($attachment_id) || !$attachment_id) {
      return new WP_Error('arkiv_upload_failed', 'Upload failed');
    }

    $pdf_ids = get_post_meta($post_id, self::META_PDF_IDS, true);
    if (!is_array($pdf_ids)) {
      $pdf_ids = [];
    }

    $pdf_ids[] = (int) $attachment_id;
    update_post_meta($post_id, self::META_PDF_IDS, $pdf_ids);

    if (!has_post_thumbnail($post_id)) {
      $gallery_ids = get_post_meta($post_id, '_arkiv_gallery_ids', true);
      if (!is_array($gallery_ids) || empty($gallery_ids)) {
        set_post_thumbnail($post_id, $attachment_id);
      }
    }

    return (int) $attachment_id;
  }

  private function get_or_create_default_mappe_term_id() {
    $taxonomy = $this->get_taxonomy_slug();
    $term = term_exists('Ukategoriserede', $taxonomy);
    if (is_array($term)) {
      return (int) $term['term_id'];
    }
    if (is_int($term)) {
      return $term;
    }

    $created = wp_insert_term('Ukategoriserede', $taxonomy);
    if (is_wp_error($created) || !isset($created['term_id'])) {
      return 0;
    }

    return (int) $created['term_id'];
  }

  private function update_post_from_request($post_id, array $request, $status = null) {
    $new_title = isset($request['arkiv_edit_title']) ? sanitize_text_field(wp_unslash($request['arkiv_edit_title'])) : '';
    $new_content = isset($request['arkiv_edit_content']) ? wp_kses_post(wp_unslash($request['arkiv_edit_content'])) : '';

    $update_data = [
      'ID' => $post_id,
      'post_title' => $new_title,
      'post_content' => $new_content,
    ];

    if ($status !== null) {
      $update_data['post_status'] = $status;
    }

    wp_update_post($update_data);

    if (array_key_exists('arkiv_edit_mappe', $request)) {
      $mappe_assigned = false;
      $mappe_input = wp_unslash($request['arkiv_edit_mappe']);
      if ($mappe_input === 'suggested') {
        $suggested = get_post_meta($post_id, self::META_SUGGESTED_FOLDER, true);
        $suggested = trim((string) $suggested);
        if ($suggested !== '') {
          $taxonomy = $this->get_taxonomy_slug();
          $term = term_exists($suggested, $taxonomy);
          if (is_array($term)) {
            $term_id = (int) $term['term_id'];
          } elseif (is_int($term)) {
            $term_id = $term;
          } else {
            $created = wp_insert_term($suggested, $taxonomy);
            $term_id = (!is_wp_error($created) && isset($created['term_id'])) ? (int) $created['term_id'] : 0;
          }

          if (!empty($term_id)) {
            wp_set_object_terms($post_id, [$term_id], $taxonomy, false);
            delete_post_meta($post_id, self::META_SUGGESTED_FOLDER);
            $mappe_assigned = true;
          } else {
            wp_set_object_terms($post_id, [], $taxonomy, false);
          }
        } else {
          $default_mappe_id = $this->get_or_create_default_mappe_term_id();
          if (!empty($default_mappe_id)) {
            wp_set_object_terms($post_id, [$default_mappe_id], $this->get_taxonomy_slug(), false);
            $mappe_assigned = true;
          } else {
            wp_set_object_terms($post_id, [], $this->get_taxonomy_slug(), false);
          }
        }
      } else {
        $mappe_id = absint($mappe_input);
        if ($mappe_id > 0) {
          wp_set_object_terms($post_id, [$mappe_id], $this->get_taxonomy_slug(), false);
          $mappe_assigned = true;
        } else {
          $default_mappe_id = $this->get_or_create_default_mappe_term_id();
          if (!empty($default_mappe_id)) {
            wp_set_object_terms($post_id, [$default_mappe_id], $this->get_taxonomy_slug(), false);
            $mappe_assigned = true;
          } else {
            wp_set_object_terms($post_id, [], $this->get_taxonomy_slug(), false);
          }
        }
      }

      if ($status === 'publish' && !$mappe_assigned) {
        $default_mappe_id = $this->get_or_create_default_mappe_term_id();
        if (!empty($default_mappe_id)) {
          wp_set_object_terms($post_id, [$default_mappe_id], $this->get_taxonomy_slug(), false);
        }
      }
    }

    $delete_ids = [];
    if (!empty($request['arkiv_delete_images'])) {
      $delete_ids = array_filter(array_map('absint', explode(',', wp_unslash($request['arkiv_delete_images']))));
    }
    $delete_pdf_ids = [];
    if (!empty($request['arkiv_delete_pdfs'])) {
      $delete_pdf_ids = array_filter(array_map('absint', explode(',', wp_unslash($request['arkiv_delete_pdfs']))));
    }

    $gallery_ids = get_post_meta($post_id, '_arkiv_gallery_ids', true);
    if (!is_array($gallery_ids)) {
      $gallery_ids = [];
    }
    $pdf_ids = get_post_meta($post_id, self::META_PDF_IDS, true);
    if (!is_array($pdf_ids)) {
      $pdf_ids = [];
    }

    if (!empty($delete_ids)) {
      $remaining = [];
      foreach ($gallery_ids as $att_id) {
        if (in_array((int) $att_id, $delete_ids, true)) {
          wp_delete_attachment((int) $att_id, true);
          continue;
        }
        $remaining[] = (int) $att_id;
      }
      $gallery_ids = $remaining;
    }

    if (!empty($delete_pdf_ids)) {
      $remaining_pdfs = [];
      foreach ($pdf_ids as $att_id) {
        if (in_array((int) $att_id, $delete_pdf_ids, true)) {
          wp_delete_attachment((int) $att_id, true);
          continue;
        }
        $remaining_pdfs[] = (int) $att_id;
      }
      $pdf_ids = $remaining_pdfs;
    }

    $order_images = [];
    if (!empty($request['arkiv_image_order'])) {
      $order_images = array_filter(array_map('absint', explode(',', wp_unslash($request['arkiv_image_order']))));
    }
    if (!empty($order_images)) {
      $ordered = [];
      $seen = [];
      foreach ($order_images as $att_id) {
        if (in_array((int) $att_id, $gallery_ids, true) && empty($seen[$att_id])) {
          $ordered[] = (int) $att_id;
          $seen[$att_id] = true;
        }
      }
      foreach ($gallery_ids as $att_id) {
        if (empty($seen[$att_id])) {
          $ordered[] = (int) $att_id;
        }
      }
      $gallery_ids = $ordered;
    }

    $order_pdfs = [];
    if (!empty($request['arkiv_pdf_order'])) {
      $order_pdfs = array_filter(array_map('absint', explode(',', wp_unslash($request['arkiv_pdf_order']))));
    }
    if (!empty($order_pdfs)) {
      $ordered = [];
      $seen = [];
      foreach ($order_pdfs as $att_id) {
        if (in_array((int) $att_id, $pdf_ids, true) && empty($seen[$att_id])) {
          $ordered[] = (int) $att_id;
          $seen[$att_id] = true;
        }
      }
      foreach ($pdf_ids as $att_id) {
        if (empty($seen[$att_id])) {
          $ordered[] = (int) $att_id;
        }
      }
      $pdf_ids = $ordered;
    }

    $new_uploads = $this->handle_images_upload($post_id);
    if (!empty($new_uploads)) {
      $gallery_ids = array_values(array_merge($gallery_ids, $new_uploads));
    }

    $new_pdfs = $this->handle_pdfs_upload($post_id);
    if (!empty($new_pdfs)) {
      $pdf_ids = array_values(array_merge($pdf_ids, $new_pdfs));
    }

    if (!empty($gallery_ids)) {
      update_post_meta($post_id, '_arkiv_gallery_ids', $gallery_ids);
    } else {
      delete_post_meta($post_id, '_arkiv_gallery_ids');
    }

    if (!empty($pdf_ids)) {
      update_post_meta($post_id, self::META_PDF_IDS, $pdf_ids);
    } else {
      delete_post_meta($post_id, self::META_PDF_IDS);
    }

    $thumbnail_candidates = array_merge($gallery_ids, $pdf_ids);

    if (array_key_exists('arkiv_featured_image', $request)) {
      $featured_input = sanitize_text_field(wp_unslash($request['arkiv_featured_image']));
      if ($featured_input === '0') {
        delete_post_thumbnail($post_id);
      } elseif ($featured_input === 'auto' || $featured_input === '') {
        if (!empty($gallery_ids)) {
          set_post_thumbnail($post_id, $gallery_ids[0]);
        } elseif (!empty($pdf_ids)) {
          set_post_thumbnail($post_id, $pdf_ids[0]);
        } else {
          delete_post_thumbnail($post_id);
        }
      } else {
        $featured_id = absint($featured_input);
        if ($featured_id && in_array((int) $featured_id, $thumbnail_candidates, true)) {
          set_post_thumbnail($post_id, $featured_id);
        } elseif (!empty($gallery_ids)) {
          set_post_thumbnail($post_id, $gallery_ids[0]);
        } elseif (!empty($pdf_ids)) {
          set_post_thumbnail($post_id, $pdf_ids[0]);
        } else {
          delete_post_thumbnail($post_id);
        }
      }
    } else {
      $thumbnail_id = get_post_thumbnail_id($post_id);
      if ($thumbnail_id && !in_array((int) $thumbnail_id, $thumbnail_candidates, true)) {
        if (!empty($gallery_ids)) {
          set_post_thumbnail($post_id, $gallery_ids[0]);
        } elseif (!empty($pdf_ids)) {
          set_post_thumbnail($post_id, $pdf_ids[0]);
        } else {
          delete_post_thumbnail($post_id);
        }
      } elseif (!$thumbnail_id && (!empty($gallery_ids) || !empty($pdf_ids))) {
        if (!empty($gallery_ids)) {
          set_post_thumbnail($post_id, $gallery_ids[0]);
        } else {
          set_post_thumbnail($post_id, $pdf_ids[0]);
        }
      }
    }
  }

  public function maybe_handle_admin_review() {
    $is_approve = !empty($_POST['arkiv_approve_btn']);
    $is_delete = !empty($_POST['arkiv_admin_delete_btn']);

    if (!$is_approve && !$is_delete) {
      return;
    }

    if (!current_user_can(self::CAP_ADMIN_MENU) || !current_user_can('edit_others_posts')) {
      return;
    }

    if (!isset($_POST['arkiv_admin_review_nonce']) || !wp_verify_nonce($_POST['arkiv_admin_review_nonce'], 'arkiv_admin_review_action')) {
      return;
    }

    $post_id = isset($_POST['arkiv_edit_post_id']) ? (int) $_POST['arkiv_edit_post_id'] : 0;
    if (!$post_id) {
      return;
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== $this->get_post_type_slug()) {
      return;
    }

    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    $redirect_url = admin_url('admin.php?page=arkiv-pending-posts');

    if ($is_delete) {
      if (!current_user_can('delete_post', $post_id)) {
        return;
      }
      $this->delete_post_with_images($post_id);
      wp_safe_redirect(add_query_arg('arkiv_review', 'deleted', $redirect_url));
      exit;
    }

    if (!current_user_can('publish_post', $post_id) && !current_user_can('publish_posts')) {
      return;
    }

    $this->update_post_from_request($post_id, $_POST, 'publish');
    wp_safe_redirect(add_query_arg('arkiv_review', 'approved', $redirect_url));
    exit;
  }

  public function render_pending_posts_page() {
    if (!current_user_can(self::CAP_ADMIN_MENU)) {
      wp_die('Du har ikke adgang til denne side.');
    }

    $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
    $notice = isset($_GET['arkiv_review']) ? sanitize_text_field(wp_unslash($_GET['arkiv_review'])) : '';

    echo '<div class="wrap">';
    echo '<h1>Afventer godkendelse</h1>';

    if ($notice === 'approved') {
      echo '<div class="notice notice-success is-dismissible"><p>Indlægget er godkendt og udgivet.</p></div>';
    } elseif ($notice === 'deleted') {
      echo '<div class="notice notice-success is-dismissible"><p>Indlægget er slettet.</p></div>';
    }

    if ($post_id) {
      $this->render_pending_post_detail($post_id);
      echo '</div>';
      return;
    }

    $pending_posts = get_posts([
      'post_type' => $this->get_post_type_slug(),
      'post_status' => 'pending',
      'posts_per_page' => 50,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);

    if (empty($pending_posts)) {
      echo '<p>Ingen indlæg afventer godkendelse.</p>';
      echo '</div>';
      return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Titel</th><th>Forfatter</th><th>Dato</th></tr></thead>';
    echo '<tbody>';

    foreach ($pending_posts as $post) {
      $edit_link = add_query_arg(
        ['page' => 'arkiv-pending-posts', 'post_id' => (int) $post->ID],
        admin_url('admin.php')
      );
      $author = get_userdata((int) $post->post_author);
      $author_name = $author ? $author->display_name : '—';
      echo '<tr>';
      echo '<td><a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title($post)) . '</a></td>';
      echo '<td>' . esc_html($author_name) . '</td>';
      echo '<td>' . esc_html(get_the_date('', $post)) . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
  }

  private function render_pending_post_detail($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== $this->get_post_type_slug()) {
      echo '<p>Indlægget blev ikke fundet.</p>';
      return;
    }

    if (!current_user_can('edit_post', $post_id)) {
      echo '<p>Du har ikke adgang til dette indlæg.</p>';
      return;
    }

    $terms = get_the_terms($post_id, $this->get_taxonomy_slug());
    $mappe_terms = get_terms([
      'taxonomy' => $this->get_taxonomy_slug(),
      'hide_empty' => false,
    ]);
    $current_mappe_id = (!empty($terms) && !is_wp_error($terms)) ? (int) $terms[0]->term_id : 0;
    $suggested = get_post_meta($post_id, self::META_SUGGESTED_FOLDER, true);
    $suggested = trim((string) $suggested);
    $mappe_selected = $current_mappe_id;
    $use_suggested = ($suggested !== '');
    if ($use_suggested) {
      $mappe_selected = 0;
    }

    $gallery_ids = get_post_meta($post_id, '_arkiv_gallery_ids', true);
    if (!is_array($gallery_ids)) {
      $gallery_ids = [];
    }
    $pdf_ids = get_post_meta($post_id, self::META_PDF_IDS, true);
    if (!is_array($pdf_ids)) {
      $pdf_ids = [];
    }
    $thumbnail_id = get_post_thumbnail_id($post_id);
    $thumbnail_candidates = array_merge($gallery_ids, $pdf_ids);
    $featured_selected = ($thumbnail_id && in_array((int) $thumbnail_id, $thumbnail_candidates, true)) ? (int) $thumbnail_id : 'auto';

    if ($post->post_status !== 'pending') {
      echo '<div class="notice notice-warning"><p>Dette indlæg er ikke længere markeret som afventende.</p></div>';
    }

    $back_link = admin_url('admin.php?page=arkiv-pending-posts');
    ?>
    <a class="button" href="<?php echo esc_url($back_link); ?>">← Tilbage til listen</a>

    <form class="arkiv-edit-form" method="post" enctype="multipart/form-data" style="margin-top:16px;">
      <?php wp_nonce_field('arkiv_admin_review_action', 'arkiv_admin_review_nonce'); ?>
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

      <div class="arkiv-edit-mappe" style="margin-top:16px;">
        <label class="arkiv-edit-label" for="arkivEditMappe">Mappe</label>
        <select id="arkivEditMappe" name="arkiv_edit_mappe" class="arkiv-edit-select">
          <option value="0">Ingen mappe</option>
          <?php if ($use_suggested) : ?>
            <option value="suggested" <?php selected(true, $use_suggested); ?>>
              <?php echo esc_html('Foreslået: ' . $suggested); ?>
            </option>
          <?php endif; ?>
          <?php if (!is_wp_error($mappe_terms)) : ?>
            <?php foreach ($mappe_terms as $term) : ?>
              <option value="<?php echo (int) $term->term_id; ?>" <?php selected($mappe_selected, (int) $term->term_id); ?>>
                <?php echo esc_html($term->name); ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>

      <div class="arkiv-edit-images" style="margin-top:18px;">
        <h2>Billeder</h2>
        <?php if (!empty($gallery_ids)) : ?>
          <div class="arkiv-edit-grid">
            <?php foreach ($gallery_ids as $att_id) :
              $thumb = wp_get_attachment_image($att_id, 'medium', false, ['class' => 'arkiv-img']);
              if (!$thumb) {
                continue;
              }
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
              if (!$thumb) {
                continue;
              }
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

      <div class="arkiv-edit-upload" style="margin-top:18px;">
        <label class="arkiv-edit-label" for="arkivEditImages">Upload flere billeder</label>
        <input id="arkivEditImages" type="file" name="arkiv_images[]" accept="image/*" multiple>
        <div class="arkiv-edit-preview" id="arkivEditPreview"></div>
      </div>

      <div class="arkiv-edit-upload" style="margin-top:18px;">
        <label class="arkiv-edit-label" for="arkivEditPdfs">Upload flere PDF'er</label>
        <input id="arkivEditPdfs" type="file" name="arkiv_pdfs[]" accept="application/pdf" multiple>
        <div class="arkiv-edit-preview" id="arkivEditPdfPreview"></div>
      </div>

      <div class="arkiv-edit-featured" style="margin-top:18px;">
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

      <div class="arkiv-edit-actions" style="margin-top:22px; display:flex; gap:12px; align-items:center;">
        <button type="submit" name="arkiv_approve_btn" value="1" class="button button-primary">Godkend</button>
        <a class="button" href="<?php echo esc_url($back_link); ?>">Annuler</a>
        <button type="submit" name="arkiv_admin_delete_btn" value="1" class="button button-secondary" style="margin-left:auto; background:#d63638; color:#fff; border-color:#b32d2e;">Slet indlæg</button>
      </div>
    </form>

    <style>
      .arkiv-edit-form textarea {
        width: 100%;
        max-width: 100%;
        border-radius: 8px;
        border: 1px solid #c3c4c7;
        padding: 10px 12px;
        font-size: 14px;
        line-height: 1.6;
      }

      .arkiv-edit-title {
        width: 100%;
        max-width: 100%;
        border-radius: 8px;
        border: 1px solid #c3c4c7;
        padding: 8px 10px;
        font-size: 14px;
        margin-bottom: 8px;
      }

      .arkiv-edit-label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
      }

      .arkiv-edit-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
      }

      .arkiv-edit-tile {
        position: relative;
        background: #fff;
        padding: 8px;
        border-radius: 8px;
        border: 1px solid #e5e5e5;
      }

      .arkiv-delete-image {
        position: absolute;
        top: 6px;
        right: 6px;
        background: rgba(17,17,17,.9);
        color: #fff;
        border: none;
        border-radius: 999px;
        padding: 4px 8px;
        font-size: 11px;
        cursor: pointer;
      }

      .arkiv-delete-pdf {
        position: absolute;
        top: 6px;
        right: 6px;
        background: rgba(17,17,17,.9);
        color: #fff;
        border: none;
        border-radius: 999px;
        padding: 4px 8px;
        font-size: 11px;
        cursor: pointer;
      }

      .arkiv-pdf-thumb {
        width: 100%;
        height: auto;
        border-radius: 8px;
        display: block;
      }

      .arkiv-edit-caption {
        margin-top: 6px;
        font-size: 12px;
        opacity: 0.8;
        word-break: break-word;
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
        border-radius: 8px;
      }

      .arkiv-edit-pdf {
        padding: 8px;
        border-radius: 8px;
        background: #fff;
        border: 1px dashed #dcdcde;
        font-size: 12px;
        word-break: break-word;
      }
    </style>

    <script>
      (function () {
        const deleteButtons = document.querySelectorAll('.arkiv-delete-image');
        const deleteInput = document.getElementById('arkivDeleteImages');
        const uploadInput = document.getElementById('arkivEditImages');
        const previewWrap = document.getElementById('arkivEditPreview');
        const deletePdfButtons = document.querySelectorAll('.arkiv-delete-pdf');
        const deletePdfInput = document.getElementById('arkivDeletePdfs');
        const pdfUploadInput = document.getElementById('arkivEditPdfs');
        const pdfPreviewWrap = document.getElementById('arkivEditPdfPreview');
        const deletePostButton = document.querySelector('button[name="arkiv_admin_delete_btn"]');

        if (deleteButtons.length && deleteInput) {
          deleteButtons.forEach(button => {
            button.addEventListener('click', function () {
              const tile = this.closest('.arkiv-edit-tile');
              if (!tile) return;
              const attId = tile.dataset.attId;
              if (!attId) return;
              const confirmDelete = window.confirm('Vil du virkelig slette billedet?');
              if (!confirmDelete) return;
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

        if (uploadInput && previewWrap) {
          uploadInput.addEventListener('change', function () {
            previewWrap.innerHTML = '';
            const files = Array.from(uploadInput.files || []).filter(file => file.type.startsWith('image/'));
            files.forEach(file => {
              if (!file.type.startsWith('image/')) {
                return;
              }
              const img = document.createElement('img');
              img.alt = file.name;
              previewWrap.appendChild(img);
              const reader = new FileReader();
              reader.onload = function (event) {
                img.src = event.target.result;
              };
              reader.readAsDataURL(file);
            });
          });
        }

        if (deletePdfButtons.length && deletePdfInput) {
          deletePdfButtons.forEach(button => {
            button.addEventListener('click', function () {
              const tile = this.closest('.arkiv-edit-tile');
              if (!tile) return;
              const attId = tile.dataset.pdfId;
              if (!attId) return;
              const confirmDelete = window.confirm('Vil du virkelig slette PDF’en?');
              if (!confirmDelete) return;
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
            pdfPreviewWrap.innerHTML = '';
            const files = Array.from(pdfUploadInput.files || []).filter(file => file.type === 'application/pdf');
            files.forEach(file => {
              const item = document.createElement('div');
              item.className = 'arkiv-edit-pdf';
              item.textContent = file.name;
              pdfPreviewWrap.appendChild(item);
            });
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
      })();
    </script>
    <?php
  }

  public function maybe_handle_edit() {
    $is_edit = !empty($_POST['arkiv_edit_btn']);
    $is_delete = !empty($_POST['arkiv_delete_btn']);

    if (!$is_edit && !$is_delete) {
      return;
    }

    if (!isset($_POST['arkiv_edit_nonce']) || !wp_verify_nonce($_POST['arkiv_edit_nonce'], 'arkiv_edit_action')) {
      return;
    }

    if (!is_user_logged_in()) {
      return;
    }

    $post_id = isset($_POST['arkiv_edit_post_id']) ? (int) $_POST['arkiv_edit_post_id'] : 0;
    if (!$post_id) {
      return;
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== $this->get_post_type_slug()) {
      return;
    }

    $is_admin = current_user_can('administrator');
    if (!$is_admin && (int) $post->post_author !== get_current_user_id()) {
      return;
    }

    if ($is_delete) {
      $this->delete_post_with_images($post_id);
      $back_page_id = (int) get_option(self::OPTION_BACK_PAGE_ID, 0);
      $back_url = $back_page_id ? get_permalink($back_page_id) : home_url('/wordpress_D/arkiv/');
      wp_safe_redirect($back_url);
      exit;
    }

    $this->update_post_from_request($post_id, $_POST);

    wp_safe_redirect(get_permalink($post_id));
    exit;
  }

  private function delete_post_with_images($post_id) {
    $this->delete_post_images($post_id);
    wp_delete_post($post_id, true);
  }

  private function delete_post_images($post_id) {
    $gallery_ids = get_post_meta($post_id, '_arkiv_gallery_ids', true);
    if (!is_array($gallery_ids)) {
      $gallery_ids = [];
    }

    $featured_id = get_post_thumbnail_id($post_id);
    $attached_ids = get_posts([
      'post_type' => 'attachment',
      'post_status' => 'inherit',
      'posts_per_page' => -1,
      'fields' => 'ids',
      'post_parent' => $post_id,
    ]);

    $attachment_ids = array_filter(array_unique(array_merge(
      $gallery_ids,
      $featured_id ? [(int) $featured_id] : [],
      is_array($attached_ids) ? $attached_ids : []
    )));

    foreach ($attachment_ids as $attachment_id) {
      wp_delete_attachment((int) $attachment_id, true);
    }
  }

  public function delete_post_images_on_admin_delete($post_id) {
    if (get_post_type($post_id) !== $this->get_post_type_slug()) {
      return;
    }

    $this->delete_post_images($post_id);
  }

  private function redirect_with($status) {
    $url = $this->get_fallback_redirect_url();

    if ($status === 'ok') {
      $redirect = $this->get_success_redirect_url($url);
      wp_safe_redirect($redirect);
      exit;
    }

    $url = remove_query_arg('arkiv_submit', $url);
    $url = add_query_arg('arkiv_submit', $status, $url);
    wp_safe_redirect($url);
    exit;
  }

  private function get_fallback_redirect_url() {
    $url = wp_get_referer();
    if (!$url) {
      $url = home_url('/');
    }
    return $url;
  }

  private function get_success_redirect_url($fallback_url) {
    $page_id = (int) get_option(self::OPTION_UPLOAD_REDIRECT_PAGE_ID, 0);
    if ($page_id) {
      $url = get_permalink($page_id);
      if ($url) {
        return $url;
      }
    }

    $fallback_url = remove_query_arg('arkiv_submit', $fallback_url);
    return add_query_arg('arkiv_submit', 'ok', $fallback_url);
  }

  public function use_mappe_template($template) {
    if (is_singular($this->get_post_type_slug())) {
      $single_template = plugin_dir_path(__FILE__) . 'templates/single-arkiv.php';
      if (file_exists($single_template)) {
        return $single_template;
      }
    }

    if (is_post_type_archive($this->get_post_type_slug())) {
      $archive_template = plugin_dir_path(__FILE__) . 'templates/archive-arkiv.php';
      if (file_exists($archive_template)) {
        return $archive_template;
      }
    }

    if (is_tax($this->get_taxonomy_slug())) {
      $plugin_template = plugin_dir_path(__FILE__) . 'templates/taxonomy-mappe.php';
      if (file_exists($plugin_template)) {
        return $plugin_template;
      }
    }

    return $template;
  }

  public function restrict_mappe_query_to_arkiv($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_tax($this->get_taxonomy_slug())) {
      $query->set('post_type', [$this->get_post_type_slug()]);
    }
  }

  private function get_post_type_slug() {
    return sanitize_key(get_option(self::OPTION_CPT_SLUG, self::CPT));
  }

  private function get_taxonomy_slug() {
    return sanitize_key(get_option(self::OPTION_TAX_SLUG, self::TAX));
  }

  private function get_logout_redirect_slug() {
    $slug = get_option(self::OPTION_LOGOUT_REDIRECT_SLUG, 'login');
    $slug = is_string($slug) ? sanitize_title($slug) : 'login';
    return $slug !== '' ? $slug : 'login';
  }

  public function render_logout_shortcode() {
    if (!is_user_logged_in()) {
      wp_safe_redirect(home_url());
      exit;
    }

    $slug = $this->get_logout_redirect_slug();
    $redirect_url = home_url('/' . trim($slug, '/') . '/');
    wp_safe_redirect(wp_logout_url($redirect_url));
    exit;
  }

  public static function get_picture_of_day_item() {
    $posts = get_posts([
      'post_type' => self::CPT,
      'post_status' => 'publish',
      'posts_per_page' => 50,
      'fields' => 'ids',
      'orderby' => 'date',
      'order' => 'DESC',
      'no_found_rows' => true,
    ]);

    if (empty($posts)) {
      return null;
    }

    $items = [];
    foreach ($posts as $post_id) {
      $gallery_ids = get_post_meta($post_id, '_arkiv_gallery_ids', true);
      if (!is_array($gallery_ids)) {
        $gallery_ids = [];
      }
      $pdf_ids = get_post_meta($post_id, '_arkiv_pdf_ids', true);
      if (!is_array($pdf_ids)) {
        $pdf_ids = [];
      }
      $thumbnail_id = get_post_thumbnail_id($post_id);
      if ($thumbnail_id) {
        $gallery_ids[] = (int) $thumbnail_id;
      }

      $attachment_ids = array_unique(array_merge($gallery_ids, $pdf_ids));
      foreach ($attachment_ids as $attachment_id) {
        $items[] = [
          'post_id' => (int) $post_id,
          'attachment_id' => (int) $attachment_id,
        ];
      }
    }

    if (empty($items)) {
      return null;
    }

    $timestamp = current_time('timestamp');
    $index = (int) date('z', $timestamp);
    $item = $items[$index % count($items)];
    $mime_type = get_post_mime_type($item['attachment_id']);
    $item['is_pdf'] = $mime_type === 'application/pdf';

    return $item;
  }
}

class Arkiv_Picture_Of_Day_Widget extends WP_Widget {
  public function __construct() {
    parent::__construct(
      'arkiv_picture_of_day_widget',
      'Arkiv: Dagens billede',
      ['description' => 'Viser et billede eller dokument fra arkivet hver dag.']
    );
  }

  public function widget($args, $instance) {
    $title = isset($instance['title']) ? $instance['title'] : 'Dagens billede';
    $item = Arkiv_Submission_Plugin::get_picture_of_day_item();

    echo $args['before_widget'];

    if ($title !== '') {
      echo $args['before_title'] . esc_html($title) . $args['after_title'];
    }

    if ($item) {
      $link = get_permalink($item['post_id']);
      $thumb = wp_get_attachment_image($item['attachment_id'], 'medium', false, [
        'class' => 'arkiv-picture-of-day-media',
      ]);

      if (!$thumb && $item['is_pdf']) {
        $icon = wp_mime_type_icon($item['attachment_id']);
        if ($icon) {
          $thumb = '<img class="arkiv-picture-of-day-media" src="' . esc_url($icon) . '" alt="">';
        }
      }

      if ($thumb) {
        echo '<a class="arkiv-picture-of-day-link" href="' . esc_url($link) . '">';
        echo $thumb;
        echo '</a>';
      } else {
        echo '<p class="arkiv-picture-of-day-empty">Ingen billede at vise endnu.</p>';
      }
    } else {
      echo '<p class="arkiv-picture-of-day-empty">Ingen indhold at vise endnu.</p>';
    }

    echo '<style>
      .arkiv-picture-of-day-link { display: block; text-decoration: none; }
      .arkiv-picture-of-day-media { width: 100%; height: auto; border-radius: 12px; display: block; }
      .arkiv-picture-of-day-empty { font-size: 14px; opacity: 0.7; }
    </style>';

    echo $args['after_widget'];
  }

  public function form($instance) {
    $title = isset($instance['title']) ? $instance['title'] : 'Dagens billede';
    ?>
    <p>
      <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Titel:</label>
      <input
        class="widefat"
        id="<?php echo esc_attr($this->get_field_id('title')); ?>"
        name="<?php echo esc_attr($this->get_field_name('title')); ?>"
        type="text"
        value="<?php echo esc_attr($title); ?>"
      >
    </p>
    <?php
  }

  public function update($new_instance, $old_instance) {
    $instance = [];
    $instance['title'] = isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
    return $instance;
  }
}

register_activation_hook(__FILE__, ['Arkiv_Submission_Plugin', 'activate']);

new Arkiv_Submission_Plugin();

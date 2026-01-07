<?php
/**
 * Plugin Name: Arkiv Submission (Community)
 * Description: Frontend indsendelse af Arkiv-indlæg med multiple billeduploads + moderation (pending).
 * Version: 1.0.0
 * Author: You
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
  const OPTION_MAPPE_KNAPPER_ENABLED = 'arkiv_mappe_knapper_enabled';
  const OPTION_CPT_SLUG = 'arkiv_cpt_slug';
  const OPTION_TAX_SLUG = 'arkiv_tax_slug';
  const OPTION_BACK_PAGE_ID = 'arkiv_back_page_id';
  const OPTION_UPLOAD_REDIRECT_PAGE_ID = 'arkiv_upload_redirect_page_id';
  const OPTION_ADMIN_BAR_ROLES = 'arkiv_admin_bar_roles';

  private $mappe_settings_page_hook = '';

  public function __construct() {
    add_shortcode('arkiv_submit', [$this, 'render_shortcode']);
    add_shortcode('mappe_knapper', [$this, 'render_mappe_knapper_shortcode']);
    add_action('init', [$this, 'maybe_handle_submit']);
    add_action('init', [$this, 'maybe_handle_edit']);
    add_action('add_meta_boxes', [$this, 'add_suggested_folder_metabox']);
    add_action('admin_menu', [$this, 'register_settings_page']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('admin_init', [$this, 'maybe_handle_mappe_settings_save']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_mappe_admin_assets']);
    add_action('wp_head', [$this, 'output_mappe_knapper_styles']);
    add_action('wp_ajax_arkiv_submit', [$this, 'handle_ajax_submit']);
    add_action('wp_ajax_arkiv_create_post', [$this, 'handle_ajax_create_post']);
    add_action('wp_ajax_arkiv_upload_image', [$this, 'handle_ajax_upload_image']);
    add_filter('template_include', [$this, 'use_mappe_template'], 99);
    add_action('pre_get_posts', [$this, 'restrict_mappe_query_to_arkiv']);
    add_action('pre_comment_on_post', [$this, 'block_anonymous_comments']);
    add_filter('show_admin_bar', [$this, 'filter_show_admin_bar'], 10, 1);
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
    ?>
    <form class="arkiv-submit-form" method="post" enctype="multipart/form-data" style="max-width:700px;">
      <?php wp_nonce_field('arkiv_submit_action', 'arkiv_submit_nonce'); ?>

      <p>
        <label><strong>Titel</strong></label><br>
        <input type="text" name="arkiv_title" required style="width:100%;padding:8px;">
      </p>

      <p>
        <label><strong>Historie</strong></label><br>
        <textarea name="arkiv_content" required rows="8" style="width:100%;padding:8px;"></textarea>
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
        <p class="arkiv-upload-status" id="arkivUploadStatus" aria-live="polite">
          <span class="arkiv-upload-status-text"></span>
          <span class="arkiv-upload-spinner" aria-hidden="true"></span>
        </p>
      </p>

      <p>
        <label>
          <input type="checkbox" name="arkiv_comments_enabled" value="1" checked>
          <strong>Kan kommenteres</strong>
        </label>
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
        const previewWrap = document.getElementById('arkivUploadPreview');
        const statusEl = document.getElementById('arkivUploadStatus');
        const statusText = statusEl ? statusEl.querySelector('.arkiv-upload-status-text') : null;
        if (!form || !uploadInput || !previewWrap || !statusEl || !statusText) return;

        const maxFiles = parseInt(uploadInput.dataset.maxFiles, 10) || 50;
        let fileMeta = [];

        function setStatus(message, busy = false) {
          statusText.textContent = message || '';
          statusEl.classList.toggle('is-busy', Boolean(busy));
        }

        function buildPreview(files) {
          previewWrap.innerHTML = '';
          fileMeta = [];
          if (!files.length) {
            setStatus('');
            return;
          }

          if (files.length > maxFiles) {
            setStatus(`Du kan max uploade ${maxFiles} filer ad gangen.`);
          } else {
            setStatus('');
          }

          files.slice(0, maxFiles).forEach(file => {
            const item = document.createElement('div');
            item.className = 'arkiv-upload-item';

            const img = document.createElement('img');
            img.className = 'arkiv-upload-thumb';
            img.alt = file.name;
            item.appendChild(img);

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

            previewWrap.appendChild(item);

            if (file.type.startsWith('image/')) {
              const reader = new FileReader();
              reader.onload = event => {
                img.src = event.target.result;
              };
              reader.readAsDataURL(file);
            }

            fileMeta.push({
              file,
              size: file.size || 0,
              bar,
              barWrap,
              state,
            });
          });
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

        function uploadSingleFile(meta, postId, nonce) {
          return new Promise(resolve => {
            const formData = new FormData();
            formData.append('action', 'arkiv_upload_image');
            formData.append('post_id', postId);
            formData.append('arkiv_submit_nonce', nonce);
            formData.append('arkiv_single_image', meta.file);

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
                setItemState(meta, 'Færdig');
                resolve(true);
                return;
              }
              setItemState(meta, 'fejl', true);
              resolve(false);
            });

            xhr.addEventListener('error', function () {
              setItemState(meta, 'fejl', true);
              resolve(false);
            });

            xhr.send(formData);
          });
        }

        uploadInput.addEventListener('change', function () {
          const files = Array.from(uploadInput.files || []);
          buildPreview(files);
        });

        form.addEventListener('submit', function (event) {
          if (!window.FormData || !window.XMLHttpRequest) {
            return;
          }

          const files = Array.from(uploadInput.files || []);
          if (files.length > maxFiles) {
            event.preventDefault();
            setStatus(`Du kan max uploade ${maxFiles} filer ad gangen.`);
            return;
          }

          event.preventDefault();
          form.classList.add('is-uploading');
          setStatus('Opretter opslag...', true);

          const formData = new FormData(form);
          formData.delete('arkiv_images[]');
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

            for (let i = 0; i < fileMeta.length; i++) {
              const meta = fileMeta[i];
              setItemState(meta, 'Uploader');
              setStatus(`Uploader ${i + 1} af ${fileMeta.length}...`);
              const ok = await uploadSingleFile(meta, postId, nonce);
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
      'show_empty' => false,
    ], $atts);

    $terms = get_terms([
      'taxonomy' => $atts['taxonomy'],
      'hide_empty' => !$atts['show_empty'],
    ]);

    if (is_wp_error($terms) || empty($terms)) {
      return '';
    }

    ob_start();
    echo '<div class="mappe-knapper">';

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
        '<a class="mappe-knap" href="%s">%s<span class="mappe-knap__text"><span class="mappe-knap__title">%s</span>%s</span></a>',
        esc_url($url),
        $image_html,
        esc_html($term->name),
        $description !== '' ? '<span class="mappe-knap__desc">' . esc_html($description) . '</span>' : ''
      );
    }

    echo '</div>';
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
      'manage_options',
      'arkiv-submission-settings',
      [$this, 'render_settings_page'],
      'dashicons-archive',
      80
    );

    add_submenu_page(
      'arkiv-submission-settings',
      'Admin bar',
      'Admin bar',
      'manage_options',
      'arkiv-admin-bar-settings',
      [$this, 'render_admin_bar_settings_page']
    );

    $this->mappe_settings_page_hook = add_submenu_page(
      'arkiv-submission-settings',
      'Mappe knapper',
      'Mappe knapper',
      'manage_options',
      'arkiv-mappe-settings',
      [$this, 'render_mappe_settings_page']
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
      self::OPTION_ADMIN_BAR_ROLES,
      [
        'type' => 'array',
        'sanitize_callback' => [$this, 'sanitize_admin_bar_roles'],
        'default' => [],
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

    $roles = wp_roles();
    $available_roles = $roles ? array_keys($roles->roles) : [];
    $sanitized = [];

    foreach ($value as $role) {
      $role = sanitize_key($role);
      if ($role !== '' && in_array($role, $available_roles, true)) {
        $sanitized[] = $role;
      }
    }

    return array_values(array_unique($sanitized));
  }

  public function filter_show_admin_bar($show) {
    if (!is_user_logged_in()) {
      return $show;
    }

    $allowed_roles = get_option(self::OPTION_ADMIN_BAR_ROLES, []);
    if (empty($allowed_roles) || !is_array($allowed_roles)) {
      return $show;
    }

    $user = wp_get_current_user();
    if (!$user || empty($user->roles)) {
      return $show;
    }

    foreach ($user->roles as $role) {
      if (in_array($role, $allowed_roles, true)) {
        return true;
      }
    }

    return false;
  }

  public function render_settings_page() {
    if (!current_user_can('manage_options')) {
      return;
    }

    $enabled = (int) get_option(self::OPTION_MAPPE_KNAPPER_ENABLED, 1);
    $cpt_slug = $this->get_post_type_slug();
    $tax_slug = $this->get_taxonomy_slug();
    $back_page_id = (int) get_option(self::OPTION_BACK_PAGE_ID, 0);
    $upload_redirect_page_id = (int) get_option(self::OPTION_UPLOAD_REDIRECT_PAGE_ID, 0);
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
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  public function render_mappe_settings_page() {
    if (!current_user_can('manage_options')) {
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

  public function render_admin_bar_settings_page() {
    if (!current_user_can('manage_options')) {
      return;
    }

    $roles = wp_roles();
    $available_roles = $roles ? $roles->roles : [];
    $allowed_roles = get_option(self::OPTION_ADMIN_BAR_ROLES, []);
    $allowed_roles = is_array($allowed_roles) ? $allowed_roles : [];
    ?>
    <div class="wrap">
      <h1>Admin bar</h1>
      <p>Vælg hvilke roller der må se den øverste WordPress værktøjslinje.</p>
      <form method="post" action="options.php">
        <?php settings_fields('arkiv_submission_settings'); ?>
        <table class="form-table" role="presentation">
          <?php if (!empty($available_roles)) : ?>
            <?php foreach ($available_roles as $role_key => $role_data) : ?>
              <tr>
                <th scope="row"><?php echo esc_html($role_data['name']); ?></th>
                <td>
                  <label>
                    <input
                      type="checkbox"
                      name="<?php echo esc_attr(self::OPTION_ADMIN_BAR_ROLES); ?>[]"
                      value="<?php echo esc_attr($role_key); ?>"
                      <?php checked(in_array($role_key, $allowed_roles, true)); ?>
                    >
                    Må se værktøjslinjen
                  </label>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr>
              <th scope="row">Roller</th>
              <td>Ingen roller fundet.</td>
            </tr>
          <?php endif; ?>
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

    if (!current_user_can('manage_options')) {
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

    if ($include_images) {
      $attachment_ids = $this->handle_images_upload($post_id);

      if (!empty($attachment_ids)) {
        set_post_thumbnail($post_id, $attachment_ids[0]);
        update_post_meta($post_id, '_arkiv_gallery_ids', $attachment_ids);
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

    if ((int) $post->post_author !== get_current_user_id()) {
      return;
    }

    if ($is_delete) {
      $this->delete_post_with_images($post_id);
      $back_page_id = (int) get_option(self::OPTION_BACK_PAGE_ID, 0);
      $back_url = $back_page_id ? get_permalink($back_page_id) : home_url('/wordpress_D/arkiv/');
      wp_safe_redirect($back_url);
      exit;
    }

    $new_content = isset($_POST['arkiv_edit_content']) ? wp_kses_post($_POST['arkiv_edit_content']) : '';

    wp_update_post([
      'ID' => $post_id,
      'post_content' => $new_content,
    ]);

    $delete_ids = [];
    if (!empty($_POST['arkiv_delete_images'])) {
      $delete_ids = array_filter(array_map('absint', explode(',', wp_unslash($_POST['arkiv_delete_images']))));
    }

    $gallery_ids = get_post_meta($post_id, '_arkiv_gallery_ids', true);
    if (!is_array($gallery_ids)) {
      $gallery_ids = [];
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

    $new_uploads = $this->handle_images_upload($post_id);
    if (!empty($new_uploads)) {
      $gallery_ids = array_values(array_merge($gallery_ids, $new_uploads));
    }

    if (!empty($gallery_ids)) {
      update_post_meta($post_id, '_arkiv_gallery_ids', $gallery_ids);
    } else {
      delete_post_meta($post_id, '_arkiv_gallery_ids');
    }

    if (array_key_exists('arkiv_featured_image', $_POST)) {
      $featured_input = sanitize_text_field(wp_unslash($_POST['arkiv_featured_image']));
      if ($featured_input === '0') {
        delete_post_thumbnail($post_id);
      } elseif ($featured_input === 'auto' || $featured_input === '') {
        if (!empty($gallery_ids)) {
          set_post_thumbnail($post_id, $gallery_ids[0]);
        } else {
          delete_post_thumbnail($post_id);
        }
      } else {
        $featured_id = absint($featured_input);
        if ($featured_id && in_array((int) $featured_id, $gallery_ids, true)) {
          set_post_thumbnail($post_id, $featured_id);
        } elseif (!empty($gallery_ids)) {
          set_post_thumbnail($post_id, $gallery_ids[0]);
        } else {
          delete_post_thumbnail($post_id);
        }
      }
    } else {
      $thumbnail_id = get_post_thumbnail_id($post_id);
      if ($thumbnail_id && !in_array((int) $thumbnail_id, $gallery_ids, true)) {
        if (!empty($gallery_ids)) {
          set_post_thumbnail($post_id, $gallery_ids[0]);
        } else {
          delete_post_thumbnail($post_id);
        }
      } elseif (!$thumbnail_id && !empty($gallery_ids)) {
        set_post_thumbnail($post_id, $gallery_ids[0]);
      }
    }

    wp_safe_redirect(get_permalink($post_id));
    exit;
  }

  private function delete_post_with_images($post_id) {
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

    wp_delete_post($post_id, true);
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
}

new Arkiv_Submission_Plugin();

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

  private $mappe_settings_page_hook = '';

  public function __construct() {
    add_shortcode('arkiv_submit', [$this, 'render_shortcode']);
    add_shortcode('mappe_knapper', [$this, 'render_mappe_knapper_shortcode']);
    add_action('init', [$this, 'maybe_handle_submit']);
    add_action('add_meta_boxes', [$this, 'add_suggested_folder_metabox']);
    add_action('admin_menu', [$this, 'register_settings_page']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('admin_init', [$this, 'maybe_handle_mappe_settings_save']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_mappe_admin_assets']);
    add_action('wp_head', [$this, 'output_mappe_knapper_styles']);
    add_filter('template_include', [$this, 'use_mappe_template'], 99);
    add_action('pre_get_posts', [$this, 'restrict_mappe_query_to_arkiv']);
    add_action('pre_comment_on_post', [$this, 'block_anonymous_comments']);
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
    <form method="post" enctype="multipart/form-data" style="max-width:700px;">
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
        <input type="file" name="arkiv_images[]" accept="image/*" multiple>
        <br><small>Tip: Vælg gerne 1–10 billeder. Første billede bruges som forsidebillede.</small>
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

  public function render_settings_page() {
    if (!current_user_can('manage_options')) {
      return;
    }

    $enabled = (int) get_option(self::OPTION_MAPPE_KNAPPER_ENABLED, 1);
    $cpt_slug = $this->get_post_type_slug();
    $tax_slug = $this->get_taxonomy_slug();
    $back_page_id = (int) get_option(self::OPTION_BACK_PAGE_ID, 0);
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

    $title = isset($_POST['arkiv_title']) ? sanitize_text_field($_POST['arkiv_title']) : '';
    $content = isset($_POST['arkiv_content']) ? wp_kses_post($_POST['arkiv_content']) : '';
    $term_id = isset($_POST['arkiv_folder_term']) ? (int) $_POST['arkiv_folder_term'] : 0;
    $suggest = isset($_POST['arkiv_suggested_folder']) ? sanitize_text_field($_POST['arkiv_suggested_folder']) : '';
    $comments_enabled = !empty($_POST['arkiv_comments_enabled']);

    if (trim($title) === '' || trim(wp_strip_all_tags($content)) === '') {
      $this->redirect_with('err');
    }

    // Opret Arkiv-indlæg som Pending Review
    $post_id = wp_insert_post([
      'post_type' => $this->get_post_type_slug(),
      'post_title' => $title,
      'post_content' => $content,
      'comment_status' => $comments_enabled ? 'open' : 'closed',
      'post_status' => 'pending',
      'post_author' => get_current_user_id(),
    ], true);

    if (is_wp_error($post_id) || !$post_id) {
      $this->redirect_with('err');
    }

    // Gem foreslået mappe (admin kan se det)
    if ($suggest !== '') {
      update_post_meta($post_id, self::META_SUGGESTED_FOLDER, $suggest);
    }

    // Sæt valgt mappe-term hvis valgt
    if ($term_id > 0) {
      wp_set_object_terms($post_id, [$term_id], $this->get_taxonomy_slug(), false);
    }

    // Håndter billeduploads
    $attachment_ids = $this->handle_images_upload($post_id);

    // Sæt featured image som første upload
    if (!empty($attachment_ids)) {
      set_post_thumbnail($post_id, $attachment_ids[0]);
      // Gem evt. alle ids som meta hvis du vil bruge dem i template
      update_post_meta($post_id, '_arkiv_gallery_ids', $attachment_ids);
    }

    $this->redirect_with('ok');
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
    $max = 10;
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

  private function redirect_with($status) {
    $url = wp_get_referer();
    if (!$url) {
      $url = home_url('/');
    }

    $url = remove_query_arg('arkiv_submit', $url);
    $url = add_query_arg('arkiv_submit', $status, $url);

    wp_safe_redirect($url);
    exit;
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

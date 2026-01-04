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

  public function __construct() {
    add_shortcode('arkiv_submit', [$this, 'render_shortcode']);
    add_action('init', [$this, 'maybe_handle_submit']);
  }

  public function render_shortcode($atts) {
    if (!is_user_logged_in()) {
      return '<p>Du skal være logget ind for at indsende.</p>';
    }

    $terms = get_terms([
      'taxonomy' => self::TAX,
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
        <button type="submit" name="arkiv_submit_btn" value="1" style="padding:10px 14px;">
          Send til godkendelse
        </button>
      </p>
    </form>
    <?php
    return ob_get_clean();
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

    if (trim($title) === '' || trim(wp_strip_all_tags($content)) === '') {
      $this->redirect_with('err');
    }

    // Opret Arkiv-indlæg som Pending Review
    $post_id = wp_insert_post([
      'post_type' => self::CPT,
      'post_title' => $title,
      'post_content' => $content,
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
      wp_set_object_terms($post_id, [$term_id], self::TAX, false);
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
}

new Arkiv_Submission_Plugin();

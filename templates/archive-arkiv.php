<?php
$tax_slug = sanitize_key(get_option('arkiv_tax_slug', 'mappe'));
get_header();
?>
<main class="arkiv-archive">
  <style>
    .arkiv-archive { padding: 24px 16px; }
    .arkiv-archive-wrap { max-width: 1200px; margin: 0 auto; }
    .arkiv-archive h1 { margin: 0 0 12px; font-size: 34px; }

    .arkiv-archive-search {
      margin: 16px 0 18px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .arkiv-archive-search input {
      width: 100%;
      max-width: 520px;
      padding: 10px 12px;
      border: 1px solid #d7d7d7;
      border-radius: 8px;
      font-size: 16px;
    }

    .arkiv-archive-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 16px;
    }

    .arkiv-archive-card {
      display: flex;
      flex-direction: column;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      overflow: hidden;
      text-decoration: none;
      color: inherit;
    }

    .arkiv-archive-thumb {
      width: 100%;
      height: 170px;
      background: #f2f2f2;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .arkiv-archive-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .arkiv-archive-content {
      padding: 14px 16px 18px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .arkiv-archive-title {
      margin: 0;
      font-size: 18px;
      line-height: 1.3;
    }

    .arkiv-archive-meta {
      font-size: 12px;
      color: #666;
    }

    .arkiv-archive-excerpt {
      font-size: 14px;
      color: #333;
    }

    .arkiv-archive-empty {
      margin-top: 24px;
      font-size: 16px;
    }
  </style>

  <div class="arkiv-archive-wrap">
    <?php the_archive_title('<h1>', '</h1>'); ?>

    <div class="arkiv-archive-search">
      <label class="screen-reader-text" for="arkivArchiveSearch">Søg i arkivet</label>
      <input
        id="arkivArchiveSearch"
        type="search"
        placeholder="Søg efter indlæg"
        aria-describedby="arkivArchiveSearchHelp"
      >
      <div id="arkivArchiveSearchHelp" class="screen-reader-text">
        Resultaterne opdateres, mens du skriver.
      </div>
    </div>

    <div class="arkiv-archive-grid">
      <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <?php
        $post_id = get_the_ID();
        $terms = get_the_terms($post_id, $tax_slug);
        $term_names = [];
        if (!empty($terms) && !is_wp_error($terms)) {
          foreach ($terms as $term) {
            $term_names[] = $term->name;
          }
        }
        $excerpt = trim(get_the_excerpt());
        if ($excerpt === '') {
          $excerpt = wp_trim_words(wp_strip_all_tags(get_the_content()), 18);
        }
        ?>
        <a
          class="arkiv-archive-card"
          href="<?php the_permalink(); ?>"
          data-title="<?php echo esc_attr(wp_strip_all_tags(get_the_title())); ?>"
          data-excerpt="<?php echo esc_attr(wp_strip_all_tags($excerpt)); ?>"
          data-mappe="<?php echo esc_attr(implode(' ', $term_names)); ?>"
        >
          <div class="arkiv-archive-thumb">
            <?php if (has_post_thumbnail()) : ?>
              <?php the_post_thumbnail('medium_large', ['loading' => 'lazy']); ?>
            <?php else : ?>
              <span>Ingen billede</span>
            <?php endif; ?>
          </div>
          <div class="arkiv-archive-content">
            <h2 class="arkiv-archive-title"><?php the_title(); ?></h2>
            <?php if (!empty($term_names)) : ?>
              <div class="arkiv-archive-meta">
                <?php echo esc_html(implode(', ', $term_names)); ?>
              </div>
            <?php endif; ?>
            <?php if ($excerpt !== '') : ?>
              <div class="arkiv-archive-excerpt"><?php echo esc_html($excerpt); ?></div>
            <?php endif; ?>
          </div>
        </a>
      <?php endwhile; else : ?>
        <p class="arkiv-archive-empty">Der er ingen indlæg i arkivet endnu.</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    (function () {
      const searchInput = document.getElementById('arkivArchiveSearch');
      const cards = Array.from(document.querySelectorAll('.arkiv-archive-card'));
      if (!searchInput || !cards.length) {
        return;
      }

      const normalized = cards.map((card) => ({
        card,
        title: (card.dataset.title || '').toLowerCase(),
        excerpt: (card.dataset.excerpt || '').toLowerCase(),
        mappe: (card.dataset.mappe || '').toLowerCase(),
      }));

      const applyFilter = () => {
        const query = searchInput.value.trim().toLowerCase();
        normalized.forEach((item) => {
          if (!query) {
            item.card.style.display = '';
            return;
          }
          const match = item.title.includes(query) || item.excerpt.includes(query) || item.mappe.includes(query);
          item.card.style.display = match ? '' : 'none';
        });
      };

      searchInput.addEventListener('input', applyFilter);
    })();
  </script>
</main>
<?php
get_footer();

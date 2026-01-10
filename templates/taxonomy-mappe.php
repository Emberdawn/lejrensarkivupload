<?php
get_header();
?>
<main style="max-width:1200px;margin:0 auto;padding:20px;">
  <style>
    .arkiv-nav { margin: 12px 0 20px; }
    .arkiv-back {
      display: inline-flex; padding: 10px 14px; border-radius: 999px;
      background: #f2f2f2; text-decoration: none;
    }
    .mappe-knapper {
      margin-top: 16px;
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
  </style>

  <?php
  $back_page_id = (int) get_option('arkiv_back_page_id', 0);
  $back_url = $back_page_id ? get_permalink($back_page_id) : '';
  if (!$back_url) {
    $back_url = home_url('/wordpress_D/arkiv/');
  }
  ?>
  <div class="arkiv-nav">
    <a class="arkiv-back" href="<?php echo esc_url($back_url); ?>">
      ← Tilbage til arkivet
    </a>
  </div>

  <?php the_archive_title('<h1>', '</h1>'); ?>

  <div class="mappe-search">
    <label class="screen-reader-text" for="mappeSearchInput">Søg i mapper</label>
    <input
      id="mappeSearchInput"
      class="mappe-search__input"
      type="search"
      placeholder="Søg efter indlæg"
      aria-describedby="mappeSearchHelp"
    >
    <div id="mappeSearchHelp" class="screen-reader-text">
      Resultaterne opdateres, mens du skriver.
    </div>
  </div>

  <div class="mappe-knapper">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
      <a
        class="mappe-knap"
        href="<?php the_permalink(); ?>"
        data-mappe-title="<?php echo esc_attr(wp_strip_all_tags(get_the_title())); ?>"
        data-mappe-desc="<?php echo esc_attr(wp_strip_all_tags(get_the_excerpt())); ?>"
      >
        <?php if (has_post_thumbnail()) { ?>
          <?php the_post_thumbnail('medium', ['class' => 'mappe-knap__image', 'loading' => 'lazy']); ?>
        <?php } ?>
        <span class="mappe-knap__text">
          <span class="mappe-knap__title"><?php the_title(); ?></span>
          <?php $excerpt = trim(get_the_excerpt()); ?>
          <?php if ($excerpt !== '') : ?>
            <span class="mappe-knap__desc"><?php echo esc_html($excerpt); ?></span>
          <?php endif; ?>
        </span>
      </a>
    <?php endwhile; else: ?>
      <p>Der er ingen indlæg i denne mappe endnu.</p>
    <?php endif; ?>
  </div>
  <script>
    (function () {
      const searchInput = document.getElementById('mappeSearchInput');
      const container = document.querySelector('.mappe-knapper');
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
</main>
<?php
get_footer();

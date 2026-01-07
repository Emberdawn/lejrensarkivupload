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

  <div class="mappe-knapper">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
      <a class="mappe-knap" href="<?php the_permalink(); ?>">
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
</main>
<?php
get_footer();

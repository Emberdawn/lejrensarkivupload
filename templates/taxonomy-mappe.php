<?php
get_header();
?>
<main style="max-width:1200px;margin:0 auto;padding:20px;">
  <?php the_archive_title('<h1>', '</h1>'); ?>

  <style>
    .arkiv-nav { margin: 12px 0 20px; }
    .arkiv-back {
      display: inline-flex; padding: 10px 14px; border-radius: 999px;
      background: #f2f2f2; text-decoration: none;
    }
    .masonry { column-count: 3; column-gap: 16px; }
    @media (max-width: 1024px) { .masonry { column-count: 2; } }
    @media (max-width: 640px)  { .masonry { column-count: 1; } }
    .tile { break-inside: avoid; margin: 0 0 16px; display: block; text-decoration: none; color: inherit; }
    .tile img { width: 100%; height: auto; display: block; border-radius: 12px; }
    .tile h3 { margin: 8px 0 0; font-size: 16px; }
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

  <div class="masonry">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
      <a class="tile" href="<?php the_permalink(); ?>">
        <?php if (has_post_thumbnail()) { the_post_thumbnail('large'); } ?>
        <h3><?php the_title(); ?></h3>
      </a>
    <?php endwhile; else: ?>
      <p>Der er ingen indlæg i denne mappe endnu.</p>
    <?php endif; ?>
  </div>
</main>
<?php
get_footer();

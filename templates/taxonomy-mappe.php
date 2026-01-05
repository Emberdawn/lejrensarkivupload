<?php
get_header();
?>
<main style="max-width:1200px;margin:0 auto;padding:20px;">
  <?php the_archive_title('<h1>', '</h1>'); ?>

  <style>
    .masonry { column-count: 3; column-gap: 16px; }
    @media (max-width: 1024px) { .masonry { column-count: 2; } }
    @media (max-width: 640px)  { .masonry { column-count: 1; } }
    .tile { break-inside: avoid; margin: 0 0 16px; display: block; text-decoration: none; color: inherit; }
    .tile img { width: 100%; height: auto; display: block; border-radius: 12px; }
    .tile h3 { margin: 8px 0 0; font-size: 16px; }
  </style>

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

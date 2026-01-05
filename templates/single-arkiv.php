<?php
get_header();
?>
<main style="max-width:1200px;margin:0 auto;padding:20px;">
  <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <article>
      <h1><?php the_title(); ?></h1>
      <?php if (has_post_thumbnail()) : ?>
        <div style="margin:16px 0;">
          <?php the_post_thumbnail('large'); ?>
        </div>
      <?php endif; ?>
      <div>
        <?php the_content(); ?>
      </div>
    </article>
  <?php endwhile; else: ?>
    <p>Indlægget blev ikke fundet.</p>
  <?php endif; ?>
</main>
<?php
get_footer();

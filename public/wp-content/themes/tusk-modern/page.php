<?php
/**
 * Template for static pages.
 */


require __DIR__ . '/header.php';
?>

<div class="hero" style="padding: var(--spacing-xl) 0 var(--spacing-md);">
    <div class="container">
        <h1 style="font-size: clamp(2.5rem, 5vw, 4rem); margin-bottom: 0;">
            <?php echo htmlspecialchars($post->title); ?>
        </h1>
    </div>
</div>

<div class="container">
    <article class="single-post-content" style="padding-top: var(--spacing-md);">
        <?php echo function_exists('tusk_render_blocks') ? tusk_render_blocks($post->content) : $post->content; ?>
    </article>
</div>

<?php require __DIR__ . '/footer.php'; ?>

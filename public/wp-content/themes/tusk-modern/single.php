<?php
/**
 * Template for single articles (posts).
 */


require_once __DIR__ . '/header.php';
?>

<div class="single-post-header">
    <div class="container">
        <div class="post-meta" style="justify-content: center; margin-bottom: var(--spacing-sm);">
            <span>📅 <?php echo $post->createdAt->format('d \d\e F, Y'); ?></span>
        </div>
        <h1 style="font-size: clamp(2.5rem, 5vw, 4rem); margin-bottom: var(--spacing-lg);">
            <?php echo htmlspecialchars($post->title); ?>
        </h1>
    </div>
</div>

<div class="container">
    <article class="single-post-content">
        <!-- Rendering content, converting from JSON (Editor.js) to HTML -->
        <?php echo function_exists('tusk_render_blocks') ? tusk_render_blocks($post->content) : $post->content; ?>
    </article>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

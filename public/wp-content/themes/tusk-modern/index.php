<?php
/**
 * The main theme template.
 * Used for the home page, blog, and archives.
 */

// Includes the header
require_once __DIR__ . '/header.php';
?>

<div class="hero">
    <div class="container">
        <h1>Build the future with TuskPress</h1>
        <p>An ultra-fast, modern content management system powered by Go & PHP 8. Designed for those who demand the best in performance and aesthetics.</p>
    </div>
</div>

<div class="container">
    <div class="post-grid">
        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <div class="post-meta">
                        <span>📅 <?php echo $post->createdAt->format('d/m/Y'); ?></span>
                    </div>
                    <h2 class="post-title">
                        <a href="/<?php echo htmlspecialchars($post->slug); ?>">
                            <?php echo htmlspecialchars($post->title); ?>
                        </a>
                    </h2>
                    <div class="post-excerpt">
                        <?php 
                            $htmlContent = function_exists('tusk_render_blocks') ? tusk_render_blocks($post->content) : $post->content;
                            $textContent = strip_tags($htmlContent);
                            echo htmlspecialchars(mb_substr($textContent, 0, 120)) . '...'; 
                        ?>
                    </div>
                    <a href="/<?php echo htmlspecialchars($post->slug); ?>" class="btn btn-primary" style="align-self: flex-start; margin-top: auto;">
                        Read Article
                    </a>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No posts found.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Includes the footer
require_once __DIR__ . '/footer.php';
?>

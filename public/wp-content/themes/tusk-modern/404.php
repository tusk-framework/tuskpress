<?php
/**
 * Template for 404 page (Not Found).
 */
require_once __DIR__ . '/header.php';
?>

<div class="hero" style="min-height: 70vh; display: flex; flex-direction: column; justify-content: center; align-items: center;">
    <div class="container" style="text-align: center;">
        <h1 style="font-size: clamp(4rem, 10vw, 8rem); margin-bottom: 0; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            404
        </h1>
        <h2 style="margin-bottom: var(--spacing-md);">Page Not Found</h2>
        <p style="margin-bottom: var(--spacing-lg);">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
        
        <a href="/" class="btn btn-primary">Back to home</a>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

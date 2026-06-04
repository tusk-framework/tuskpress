<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TuskPress - A modern, blazingly fast CMS built with PHP and Go.">
    
    <title>
        <?php 
        if (!empty($is_single) || !empty($is_page)) {
            echo htmlspecialchars($post->title) . ' | TuskPress';
        } else {
            echo 'TuskPress - CMS Moderno e Rápido';
        }
        ?>
    </title>

    <!-- Theme Stylesheet -->
    <link rel="stylesheet" href="/wp-content/themes/tusk-modern/style.css">
    
    <?php if (isset($hookBus)) $hookBus->dispatch('theme.head'); ?>
</head>
<body>

    <header class="navbar">
        <div class="container">
            <a href="/" class="navbar-brand">TuskPress</a>
            <nav>
                <ul class="navbar-nav">
                    <li><a href="/" class="nav-link">Home</a></li>
                    <li><a href="/hello-world" class="nav-link">Sobre</a></li>
                    <li><a href="/wp-login" class="nav-link">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main-content">

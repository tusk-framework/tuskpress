    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> TuskPress. Todos os direitos reservados.</p>
            <p style="font-size: 0.875rem; opacity: 0.7;">Powered by Tusk Engine &amp; PHP 8</p>
        </div>
    </footer>

    <?php if (isset($hookBus)) $hookBus->dispatch('theme.footer'); ?>
</body>
</html>

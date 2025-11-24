<footer class="border-t border-border">
    <div class="container mx-auto px-4 py-6">
        <p class="text-center text-sm text-muted-foreground">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'ZuzShortURL'); ?>. 
            Powered by <a href="https://github.com/JanePHPDev/ZuzShortURL-Next" target="_blank" rel="noopener noreferrer" class="font-medium underline underline-offset-4">ZuzShortURL</a>.
        </p>
    </div>
</footer>

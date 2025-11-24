<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
?>
<header class="fixed top-0 left-0 right-0 z-50 bg-white/80 dark:bg-zinc-900/80 border-b border-gray-200/80 dark:border-zinc-800/80 backdrop-blur-md">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <a href="/" class="text-xl font-semibold text-zinc-800 dark:text-white">
                <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'ZuzShortURL'); ?>
            </a>

            <!-- Desktop Nav -->
            <nav class="hidden md:flex items-center space-x-6">
                <a href="/#features" class="text-sm font-medium text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white transition-colors">功能</a>
                <a href="/#pricing" class="text-sm font-medium text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white transition-colors">定价</a>
                <a href="https://github.com/JanePHPDev/ZuzShortURL" target="_blank" class="text-sm font-medium text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white transition-colors">GitHub</a>
                <a href="/api/docs" class="text-sm font-medium text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white transition-colors">API</a>
            </nav>

            <div class="hidden md:flex items-center space-x-2">
                <?php if (is_logged_in()): ?>
                    <a href="/dashboard" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-9 px-4 py-2 bg-zinc-800 text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                        控制台
                    </a>
                <?php else: ?>
                    <a href="/login" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-9 px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        登录
                    </a>
                    <a href="/register" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-9 px-4 py-2 bg-zinc-800 text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                        免费注册
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <button id="mobile-menu-button" class="p-2 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Panel -->
    <div id="mobile-menu" class="hidden md:hidden absolute top-0 left-0 w-full h-screen bg-white dark:bg-zinc-900">
        <div class="flex justify-between items-center h-16 px-4 border-b border-gray-200 dark:border-zinc-800">
            <a href="/" class="text-xl font-semibold text-zinc-800 dark:text-white">
                <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'ZuzShortURL'); ?>
            </a>
            <button id="mobile-menu-close-button" class="p-2 text-zinc-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <nav class="flex flex-col items-center justify-center space-y-8 mt-8">
            <a href="/#features" class="mobile-menu-link text-lg font-medium text-zinc-600 dark:text-zinc-300">功能</a>
            <a href="/#pricing" class="mobile-menu-link text-lg font-medium text-zinc-600 dark:text-zinc-300">定价</a>
            <a href="https://github.com/JanePHPDev/ZuzShortURL" target="_blank" class="mobile-menu-link text-lg font-medium text-zinc-600 dark:text-zinc-300">GitHub</a>
            <a href="/api/docs" class="mobile-menu-link text-lg font-medium text-zinc-600 dark:text-zinc-300">API</a>
            <div class="w-full h-px bg-gray-200 dark:bg-zinc-800 my-4"></div>
            <?php if (is_logged_in()): ?>
                <a href="/dashboard" class="mobile-menu-link w-full mx-auto max-w-xs text-center inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-10 px-4 py-2 bg-zinc-800 text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                    控制台
                </a>
            <?php else: ?>
                <a href="/login" class="mobile-menu-link w-full mx-auto max-w-xs text-center inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-10 px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    登录
                </a>
                <a href="/register" class="mobile-menu-link w-full mx-auto max-w-xs text-center inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-10 px-4 py-2 bg-zinc-800 text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                    免费注册
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenuCloseButton = document.getElementById('mobile-menu-close-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobileMenuLinks = document.querySelectorAll('.mobile-menu-link');

    const openMenu = () => {
        mobileMenu.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent scrolling background
    };

    const closeMenu = () => {
        mobileMenu.classList.add('hidden');
        document.body.style.overflow = '';
    };

    mobileMenuButton.addEventListener('click', openMenu);
    mobileMenuCloseButton.addEventListener('click', closeMenu);
    mobileMenuLinks.forEach(link => {
        link.addEventListener('click', closeMenu);
    });
});
</script>
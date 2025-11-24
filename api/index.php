<?php
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
if ($path === '/migrate') {
    require __DIR__ . '/migrate.php';
    exit;
}

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

$clean_path = ltrim($path, '/');
$file_path = __DIR__ . DIRECTORY_SEPARATOR . $clean_path;

if (strpos($path, '.') !== false && file_exists($file_path) && is_file($file_path)) {
    $ext = pathinfo($clean_path, PATHINFO_EXTENSION);
    $mime = match($ext) {
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png', 'jpg', 'jpeg', 'gif' => 'image/' . $ext,
        default => 'application/octet-stream'
    };
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=3600');
    header('ETag: "' . md5_file($file_path) . '"');
    readfile($file_path);
    exit;
}

require_once 'includes/config.php';
require_once 'includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];

$private_mode = ($settings['private_mode'] ?? 'false') === 'true';
$require_admin = $private_mode && !require_admin_auth();

if ($require_admin && in_array($path, ['/', '/create', '/login', '/register', '/dashboard'])) {
    header('Location: /admin');
    exit;
}

$current_host = $_SERVER['HTTP_HOST'];
$official_domain = get_setting($pdo, 'official_domain') ?? $current_host;
$short_domain = get_setting($pdo, 'short_domain') ?? $current_host;

$is_official = $current_host === $official_domain;
$is_short = $current_host === $short_domain;

$excluded_paths = ['/create', '/admin', '/login', '/register', '/dashboard', '/logout', '/api/docs'];
$short_code_match = preg_match('/^\/([A-Za-z0-9]{5,10})$/', $path, $matches);
$is_short_code = $short_code_match && !in_array($path, $excluded_paths);

if ($enable_dual_domain && $is_official && $is_short_code) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

if ($enable_dual_domain && $is_short) {
    if ($path === '/' || $path === '') {
        header('Location: ' . $official_url);
        exit;
    } elseif (!$is_short_code) {
        http_response_code(404);
        echo '404 Not Found';
        exit;
    }
}

if ($path === '/' || $path === '') {
    $history = [];
    if (isset($_COOKIE['short_history'])) {
        $history = json_decode($_COOKIE['short_history'], true) ?: [];
    }
    $allow_guest = get_setting($pdo, 'allow_guest') === 'true';
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN" class="scroll-smooth">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars(get_setting($pdo, 'site_title')); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="includes/styles.css">
        <script>
            tailwind.config = {
                darkMode: 'media', // or 'class'
                theme: {
                    extend: {
                        colors: {
                            primary: '#1e40af',
                        }
                    }
                }
            }
        </script>
        <style>
            .dotted-background {
                background-color: #f1f5f9;
                background-image: radial-gradient(circle at 1px 1px, #94a3b8 1px, transparent 0);
                background-size: 20px 20px;
            }
            .dark .dotted-background {
                background-color: #020617;
                background-image: radial-gradient(circle at 1px 1px, #1e293b 1px, transparent 0);
            }
            .marquee-content {
                animation: scroll 20s linear infinite;
            }
            @keyframes scroll {
                from { transform: translateX(0); }
                to { transform: translateX(-50%); }
            }
        </style>
    </head>
    <body class="bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200">
        <?php include 'includes/header.php'; ?>

        <main>
            <!-- Hero Section -->
            <section class="relative pt-32 pb-24 md:pt-48 md:pb-32 dotted-background">
                <div class="container mx-auto px-4 text-center">
                    <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight text-zinc-900 dark:text-white leading-tight">
                        <?php echo htmlspecialchars(get_setting($pdo, 'header_title')); ?>
                    </h1>
                    <p class="mt-6 text-lg md:text-xl max-w-2xl mx-auto text-zinc-600 dark:text-zinc-400">
                        <?php echo htmlspecialchars(get_setting($pdo, 'home_description')); ?>
                    </p>
                    <div class="mt-8 flex justify-center items-center gap-4">
                        <a href="<?php echo $allow_guest ? '/create' : '/register'; ?>" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-semibold ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-11 px-8 bg-zinc-800 text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                            <?php echo $allow_guest ? '开始使用' : '立即注册'; ?>
                        </a>
                        <a href="#pricing" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-11 px-8 border border-zinc-300 dark:border-zinc-700 bg-white text-zinc-900 hover:bg-zinc-200 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                            查看定价
                        </a>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section id="features" class="py-20 md:py-28">
                <div class="container mx-auto px-4">
                    <div class="max-w-3xl mx-auto text-center mb-12">
                        <h2 class="text-3xl md:text-4xl font-bold tracking-tight">为什么选择我们？</h2>
                        <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">一个功能强大、稳定可靠的短链接平台</p>
                    </div>
                    <div class="grid md:grid-cols-3 gap-8">
                        <div class="p-8 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                            <div class="flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/50 mb-4">
                               <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-2">极速响应</h3>
                            <p class="text-zinc-600 dark:text-zinc-400">全球 CDN 加速，平均响应时间仅需 1.3 秒，让您的链接跳转快如闪电。</p>
                        </div>
                        <div class="p-8 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                            <div class="flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/50 mb-4">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-2">安全可靠</h3>
                            <p class="text-zinc-600 dark:text-zinc-400">企业级安全防护，99.9% 正常运行时间保障，让您的链接永不下线。</p>
                        </div>
                        <div class="p-8 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                            <div class="flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/50 mb-4">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-2">完全免费</h3>
                            <p class="text-zinc-600 dark:text-zinc-400">无隐藏费用，无功能限制，我们提供真正永久免费的短链接服务。</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CEO Section -->
            <section class="py-20 md:py-28 bg-zinc-100 dark:bg-zinc-800/50">
                <div class="container mx-auto px-4">
                    <div class="max-w-4xl mx-auto text-center">
                        <img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/4dec4c19b1a8b418.png" alt="CEO" class="w-24 h-24 rounded-full mx-auto mb-6">
                        <p class="text-xl md:text-2xl font-light text-zinc-800 dark:text-zinc-200 italic leading-relaxed">
                            “我们的目标是为用户提供最快速、最稳定、最安全的短链接服务，帮助他们在数字世界中更高效地分享和连接。”
                        </p>
                        <p class="mt-6 text-md font-semibold text-zinc-700 dark:text-zinc-300">JanePHPDev</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">创始人 & CEO</p>
                    </div>
                </div>
            </section>

            <!-- User Reviews Section -->
            <section class="py-20 md:py-28">
                <div class="container mx-auto px-4">
                    <div class="max-w-3xl mx-auto text-center mb-12">
                        <h2 class="text-3xl md:text-4xl font-bold tracking-tight">听听我们的用户怎么说</h2>
                        <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">我们为成千上万的用户提供了优质服务</p>
                    </div>
                    <div class="marquee-container relative overflow-hidden">
                        <div class="flex flex-col">
                            <div class="marquee-content flex">
                                <?php for ($i = 0; $i < 2; $i++): ?>
                                    <div class="review-card flex-shrink-0 w-80 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-6 mx-4">
                                        <p class="text-zinc-700 dark:text-zinc-300">“这个短链接服务真的太棒了！界面简洁，操作方便，而且速度超快。”</p>
                                        <p class="mt-4 font-semibold text-zinc-800 dark:text-zinc-200">- 来自某电商平台的运营</p>
                                    </div>
                                    <div class="review-card flex-shrink-0 w-80 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-6 mx-4">
                                        <p class="text-zinc-700 dark:text-zinc-300">“我一直在寻找一个稳定可靠的短链接服务，ZuzShortURL完全满足了我的需求。”</p>
                                        <p class="mt-4 font-semibold text-zinc-800 dark:text-zinc-200">- 独立开发者</p>
                                    </div>
                                    <div class="review-card flex-shrink-0 w-80 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-6 mx-4">
                                        <p class="text-zinc-700 dark:text-zinc-300">“API功能非常强大，文档也很清晰，集成到我们的系统中非常顺利。”</p>
                                        <p class="mt-4 font-semibold text-zinc-800 dark:text-zinc-200">- SaaS公司技术总监</p>
                                    </div>
                                     <div class="review-card flex-shrink-0 w-80 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-6 mx-4">
                                        <p class="text-zinc-700 dark:text-zinc-300">“自从用了ZuzShortURL，我们的营销活动链接点击率提升了20%！”</p>
                                        <p class="mt-4 font-semibold text-zinc-800 dark:text-zinc-200">- 市场营销经理</p>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <div class="marquee-content flex" style="animation-direction: reverse;">
                                <?php for ($i = 0; $i < 2; $i++): ?>
                                    <div class="review-card flex-shrink-0 w-80 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-6 mx-4">
                                        <p class="text-zinc-700 dark:text-zinc-300">“这个短链接服务真的太棒了！界面简洁，操作方便，而且速度超快。”</p>
                                        <p class="mt-4 font-semibold text-zinc-800 dark:text-zinc-200">- 来自某电商平台的运营</p>
                                    </div>
                                    <div class="review-card flex-shrink-0 w-80 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-6 mx-4">
                                        <p class="text-zinc-700 dark:text-zinc-300">“我一直在寻找一个稳定可靠的短链接服务，ZuzShortURL完全满足了我的需求。”</p>
                                        <p class="mt-4 font-semibold text-zinc-800 dark:text-zinc-200">- 独立开发者</p>
                                    </div>
                                    <div class="review-card flex-shrink-0 w-80 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-6 mx-4">
                                        <p class="text-zinc-700 dark:text-zinc-300">“API功能非常强大，文档也很清晰，集成到我们的系统中非常顺利。”</p>
                                        <p class="mt-4 font-semibold text-zinc-800 dark:text-zinc-200">- SaaS公司技术总监</p>
                                    </div>
                                     <div class="review-card flex-shrink-0 w-80 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-6 mx-4">
                                        <p class="text-zinc-700 dark:text-zinc-300">“自从用了ZuzShortURL，我们的营销活动链接点击率提升了20%！”</p>
                                        <p class="mt-4 font-semibold text-zinc-800 dark:text-zinc-200">- 市场营销经理</p>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Pricing Section -->
            <section id="pricing" class="py-20 md:py-28 bg-zinc-100 dark:bg-zinc-800/50">
                <div class="container mx-auto px-4">
                    <div class="max-w-3xl mx-auto text-center mb-12">
                        <h2 class="text-3xl md:text-4xl font-bold tracking-tight">选择适合您的计划</h2>
                        <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">无论是个人使用还是企业部署，我们都有灵活的方案。</p>
                    </div>
                    <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                        <!-- Free Plan -->
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-8 flex flex-col">
                            <h3 class="text-2xl font-bold mb-2">注册用户</h3>
                            <p class="text-zinc-500 dark:text-zinc-400 mb-6">适合需要更多功能的用户</p>
                            <div class="text-4xl font-extrabold mb-6">$0<span class="text-lg font-medium text-zinc-500">/月</span></div>
                            <ul class="space-y-3 text-zinc-600 dark:text-zinc-300 flex-grow">
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>个人链接管理面板</li>
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>详细访问统计</li>
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>无限自定义短码</li>
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>自定义中继页设计</li>
                            </ul>
                            <a href="<?php echo $allow_guest ? '/create' : '/register'; ?>" class="mt-8 w-full inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-semibold h-11 px-8 bg-zinc-800 text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"><?php echo $allow_guest ? '免费体验' : '立即注册'; ?></a>
                        </div>
                        <!-- Self-Hosted Plan -->
                        <div class="border-2 border-blue-600 rounded-lg p-8 flex flex-col relative">
                            <span class="absolute top-0 -translate-y-1/2 bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full">最受欢迎</span>
                            <h3 class="text-2xl font-bold mb-2">自建托管</h3>
                            <p class="text-zinc-500 dark:text-zinc-400 mb-6">数据完全由您掌控</p>
                            <div class="text-4xl font-extrabold mb-6">$0<span class="text-lg font-medium text-zinc-500">/月</span></div>
                            <ul class="space-y-3 text-zinc-600 dark:text-zinc-300 flex-grow">
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>完全数据控制权</li>
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>一键自托管部署</li>
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>自由扩展功能</li>
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>100% 开源免费</li>
                            </ul>
                            <a href="https://github.com/JanePHPDev/ZuzShortURL" target="_blank" class="mt-8 w-full inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-semibold h-11 px-8 bg-blue-600 text-white hover:bg-blue-700">Fork 本项目</a>
                        </div>
                        <!-- Pro Plan -->
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-8 flex flex-col">
                            <h3 class="text-2xl font-bold mb-2">Pro</h3>
                            <p class="text-zinc-500 dark:text-zinc-400 mb-6">为大型企业提供高级支持</p>
                             <div class="text-4xl font-extrabold mb-6">$22<span class="text-lg font-medium text-zinc-500">/月</span></div>
                            <ul class="space-y-3 text-zinc-600 dark:text-zinc-300 flex-grow">
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>免费无限量Pages</li>
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>自定义域名</li>
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>新功能体验</li>
                                <li class="flex items-center"><svg class="w-5 h-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>高级支持</li>
                            </ul>
                            <button class="mt-8 w-full inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-semibold h-11 px-8 bg-zinc-300 text-zinc-500 cursor-not-allowed" disabled>暂未上线</button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;
} elseif ($path === '/create') {
    require 'create.php';
} elseif ($path === '/admin') {
    require 'admin.php';
} elseif ($path === '/login') {
    require 'login.php';
} elseif ($path === '/register') {
    require 'register.php';
} elseif ($path === '/dashboard') {
    require 'dashboard.php';
} elseif ($path === '/logout') {
    logout();
    header('Location: /');
    exit;
} elseif ($path === '/api/docs') {
    require 'api.php';
} elseif ($path === '/api/create' && $method === 'POST') {
    header('Content-Type: application/json');
    check_rate_limit($pdo);
    $input = json_decode(file_get_contents('php://input'), true);
    $longurl = trim($input['url'] ?? '');
    $custom_code = trim($input['custom_code'] ?? '');
    $enable_intermediate = $input['enable_intermediate'] ?? false;
    $redirect_delay = is_numeric($input['redirect_delay']) ? (int)$input['redirect_delay'] : 0;
    $expiration = $input['expiration'] ?? null;
    $response = ['success' => false];
    if (!filter_var($longurl, FILTER_VALIDATE_URL)) {
        $response['error'] = '无效的URL';
        echo json_encode($response);
        exit;
    }
    $code = '';
    if (!empty($custom_code)) {
        $validate = validate_custom_code($custom_code, $pdo, $reserved_codes);
        if ($validate !== true) {
            $response['error'] = $validate;
            echo json_encode($response);
            exit;
        }
        $code = $custom_code;
    }
    if (empty($code)) {
        try {
            $code = generate_random_code($pdo, $reserved_codes);
        } catch (Exception $e) {
            $response['error'] = '生成短码失败';
            echo json_encode($response);
            exit;
        }
    }
    $enable_str = $enable_intermediate ? 'true' : 'false';
    $stmt = $pdo->prepare("INSERT INTO short_links (shortcode, longurl, enable_intermediate_page, redirect_delay, expiration_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$code, $longurl, $enable_str, $redirect_delay, $expiration ?: null]);
    $response['success'] = true;
    $response['short_url'] = $short_url . '/' . $code;
    echo json_encode($response);
    exit;
} elseif ($short_code_match && !in_array($path, $excluded_paths)) {
    $code = $matches[1];
    require 'redirect.php';
} else {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - 未找到</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-background text-foreground min-h-screen flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-6xl font-bold text-muted-foreground mb-4">404</h1>
            <p class="text-xl text-muted-foreground mb-6">页面未找到</p>
            <a href="/" class="px-6 py-2 bg-black text-primary-foreground rounded-lg hover:bg-black/90 transition-colors">返回首页</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$handler->gc(1440);
?>
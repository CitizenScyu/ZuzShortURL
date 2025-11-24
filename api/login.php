<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$csrf_token = generate_csrf_token();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        $error = 'CSRF令牌无效。';
    } elseif (get_setting($pdo, 'turnstile_enabled') === 'true' && !validate_captcha($_POST['cf-turnstile-response'] ?? '', $pdo)) {
        $error = 'CAPTCHA验证失败。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($username) || empty($password)) {
            $error = '用户名或密码为空。';
        } else {
            $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                header('Location: /dashboard');
                exit;
            } else {
                $error = '用户名或密码错误。';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'Zuz.Asia'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="includes/styles.css">
    <script src="includes/script.js"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="bg-background text-foreground min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>
    <main class="main-content flex items-center justify-center">
        <div class="max-w-md w-full p-4">
            <div class="bg-card rounded-lg border p-6">
                <h2 class="text-2xl font-bold mb-4 text-center">登录</h2>
                <?php if ($error): ?>
                    <div class="bg-destructive/10 border border-destructive/30 text-destructive px-3 py-2 rounded-md mb-3"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="mb-3">
                        <label class="block text-sm font-medium mb-1">用户名</label>
                        <input type="text" name="username" class="w-full px-3 py-2 border border-input rounded-md bg-transparent" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">密码</label>
                        <input type="password" name="password" class="w-full px-3 py-2 border border-input rounded-md bg-transparent" required>
                    </div>
                    <?php if (get_setting($pdo, 'turnstile_enabled') === 'true'): ?>
                    <div class="cf-turnstile mb-3" data-sitekey="<?php echo htmlspecialchars(get_setting($pdo, 'turnstile_site_key')); ?>"></div>
                    <?php endif; ?>
                    <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90 mt-3">登录</button>
                </form>
                <?php if (get_setting($pdo, 'allow_register') === 'true'): ?>
                    <p class="mt-4 text-center text-sm text-muted-foreground">没有账号？ <a href="/register" class="underline">注册</a></p>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
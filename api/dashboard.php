<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (get_setting($pdo, 'private_mode') === 'true' && !require_admin_auth()) {
    header('Location: /admin');
    exit;
}

if (!is_logged_in()) {
    header('Location: /login');
    exit;
}

$user_id = get_current_user_id();
$reserved_codes = ['admin', 'help', 'about', 'api', 'login', 'register', 'logout', 'dashboard'];
$csrf_token = generate_csrf_token();
$error = '';
$success = '';
$links = [];
$sort = $_GET['sort'] ?? 'time';
$order = $sort === 'clicks' ? 'clicks DESC' : 'created_at DESC';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_rate_limit($pdo);
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        $error = 'CSRF令牌无效。';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            if (get_setting($pdo, 'turnstile_enabled') === 'true' && !validate_captcha($_POST['cf-turnstile-response'] ?? '', $pdo)) {
                $error = 'CAPTCHA验证失败。';
            } else {
                $longurl = trim($_POST['url'] ?? '');
                $custom_code = trim($_POST['custom_code'] ?? '');
                $enable_intermediate = isset($_POST['enable_intermediate']);
                $redirect_delay = is_numeric($_POST['redirect_delay']) ? (int)$_POST['redirect_delay'] : 0;
                $link_password = trim($_POST['link_password'] ?? '');
                $password_hash = !empty($link_password) ? password_hash($link_password, PASSWORD_DEFAULT) : null;
                $expiration = $_POST['expiration'] ?? null;
                
                if ($expiration && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration)) {
                    $error = '无效的过期日期格式。';
                }
                
                if (!filter_var($longurl, FILTER_VALIDATE_URL)) {
                    $error = '无效的URL。';
                } else {
                    $code = '';
                    if (!empty($custom_code)) {
                        $validate = validate_custom_code($custom_code, $pdo, $reserved_codes);
                        if ($validate === true) {
                            $code = $custom_code;
                        } else {
                            $error = $validate;
                        }
                    }
                    if (empty($code)) {
                        try {
                            $code = generate_random_code($pdo, $reserved_codes);
                        } catch (Exception $e) {
                            $error = '生成短码失败。';
                        }
                    }
                    if (empty($error)) {
                        $enable_str = $enable_intermediate ? 'true' : 'false';
                        $stmt = $pdo->prepare("INSERT INTO short_links (shortcode, longurl, user_id, enable_intermediate_page, redirect_delay, link_password, expiration_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$code, $longurl, $user_id, $enable_str, $redirect_delay, $password_hash, $expiration ?: null]);
                        $success = '链接添加成功。';
                    }
                }
            }
        } elseif ($action === 'edit') {
            $code = $_POST['code'] ?? '';
            $newurl = trim($_POST['newurl'] ?? '');
            $enable_intermediate = isset($_POST['enable_intermediate']);
            $redirect_delay = is_numeric($_POST['redirect_delay']) ? (int)$_POST['redirect_delay'] : 0;
            $link_password = trim($_POST['link_password'] ?? '');
            $password_hash = !empty($link_password) ? password_hash($link_password, PASSWORD_DEFAULT) : null;
            $expiration = $_POST['expiration'] ?? null;
            
            if ($expiration && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration)) {
                $error = '无效的过期日期格式。';
            }
            
            $stmt = $pdo->prepare("SELECT id FROM short_links WHERE shortcode = ? AND user_id = ?");
            $stmt->execute([$code, $user_id]);
            if (!$stmt->fetch()) {
                $error = '无权限编辑此链接。';
            } elseif (!filter_var($newurl, FILTER_VALIDATE_URL)) {
                $error = '无效的新URL。';
            } else {
                $enable_str = $enable_intermediate ? 'true' : 'false';
                $stmt = $pdo->prepare("UPDATE short_links SET longurl = ?, enable_intermediate_page = ?, redirect_delay = ?, link_password = ?, expiration_date = ? WHERE shortcode = ? AND user_id = ?");
                $stmt->execute([$newurl, $enable_str, $redirect_delay, $password_hash, $expiration ?: null, $code, $user_id]);
                $success = '链接更新成功。';
            }
        } elseif ($action === 'delete') {
            $code = $_POST['code'] ?? '';
            $stmt = $pdo->prepare("DELETE FROM short_links WHERE shortcode = ? AND user_id = ?");
            $stmt->execute([$code, $user_id]);
            $success = '链接删除成功。';
        } elseif ($action === 'delete_expired') {
            $stmt = $pdo->prepare("DELETE FROM short_links WHERE user_id = ? AND expiration_date < NOW()");
            $stmt->execute([$user_id]);
            $success = '已过期链接删除成功。';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM short_links WHERE user_id = ? ORDER BY $order");
$stmt->execute([$user_id]);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_links = count($links);
$total_clicks = array_sum(array_column($links, 'clicks'));
$avg_click_rate = $total_links > 0 ? round($total_clicks / $total_links, 2) : 0;

// 来源统计 (top 10)
$sources_query = $pdo->prepare("
SELECT 
  CASE 
    WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
    ELSE regexp_replace(referrer, '^(https?://)?([^/]+).*', '\\2')
  END AS domain,
  COUNT(*) as count 
FROM click_logs 
WHERE shortcode IN (SELECT shortcode FROM short_links WHERE user_id = ?)
GROUP BY domain 
ORDER BY count DESC
LIMIT 10
");
$sources_query->execute([$user_id]);
$sources = $sources_query->fetchAll(PDO::FETCH_ASSOC);

// Top 50 短码点击量
$top_query = $pdo->prepare("SELECT shortcode, clicks FROM short_links WHERE user_id = ? ORDER BY clicks DESC LIMIT 50");
$top_query->execute([$user_id]);
$top_links = $top_query->fetchAll(PDO::FETCH_ASSOC);

// 过去30天每日点击趋势
$daily_clicks_query = $pdo->prepare("
SELECT date(clicked_at) as day, COUNT(*) as count 
FROM click_logs 
WHERE shortcode IN (SELECT shortcode FROM short_links WHERE user_id = ?)
AND clicked_at >= NOW() - INTERVAL '30 days'
GROUP BY day 
ORDER BY day ASC
");
$daily_clicks_query->execute([$user_id]);
$daily_clicks_raw = $daily_clicks_query->fetchAll(PDO::FETCH_ASSOC);

$daily_clicks = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_clicks[$date] = 0;
}
foreach ($daily_clicks_raw as $row) {
    $daily_clicks[$row['day']] = (int)$row['count'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户控制台 - <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'Zuz.Asia'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="includes/script.js"></script>
    <link rel="stylesheet" href="includes/styles.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://cdn.mengze.vip/npm/chart.js"></script>
    <style>
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    @media (max-width: 768px) {
        .chart-container {
            height: 200px;
        }
    }
</style>
</head>
<body class="bg-background text-foreground min-h-screen">
    <?php include 'includes/header.php'; ?>
    <main class="main-content container mx-auto p-4">
        <?php if ($error): ?>
            <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <h1 class="text-3xl font-bold mb-6">您好, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>

        <!-- Stats Cards -->
        <div class="grid gap-4 md:grid-cols-3 mb-6">
            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <div class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <h3 class="text-sm font-medium">总链接数</h3>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-muted-foreground"><path d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.102 1.101"></path></svg>
                </div>
                <div class="text-2xl font-bold"><?php echo $total_links; ?></div>
            </div>
            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <div class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <h3 class="text-sm font-medium">总点击量</h3>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-muted-foreground"><path d="M3.5 13.1c.8 0 1.5.7 1.5 1.5v4.9c0 .6.4 1.1 1 1.1s1-.5 1-1.1v-4.9c0-.8.7-1.5 1.5-1.5s1.5.7 1.5 1.5v4.9c0 .6.4 1.1 1 1.1s1-.5 1-1.1v-4.9c0-.8.7-1.5 1.5-1.5s1.5.7 1.5 1.5v4.9c0 .6.4 1.1 1 1.1h.5m-16 4h17"></path></svg>
                </div>
                <div class="text-2xl font-bold"><?php echo $total_clicks; ?></div>
            </div>
            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <div class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <h3 class="text-sm font-medium">平均点击</h3>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-muted-foreground"><path d="M2 2v20"></path><path d="M5.5 17h2.25v2.25"></path><path d="M5.5 17h2.25v2.25"></path><path d="M9 11h2.25v2.25"></path><path d="M12.5 5h2.25v2.25"></path><path d="M16 14h2.25v2.25"></path><path d="M19.5 8h2.25v2.25"></path><path d="M2 2h20"></path><path d="M5.5 17l3-6 3 12 4-15 3 9 3-6"></path></svg>
                </div>
                <div class="text-2xl font-bold"><?php echo $avg_click_rate; ?></div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-card rounded-lg border p-6">
                <h3 class="text-lg font-semibold mb-4">每日点击趋势</h3>
                <div class="chart-container">
                    <canvas id="dailyLine"></canvas>
                </div>
            </div>
            <div class="bg-card rounded-lg border p-6">
                <h3 class="text-lg font-semibold mb-4">Top 10 来源</h3>
                <div class="chart-container">
                    <canvas id="topSourcesPie"></canvas>
                </div>
            </div>
        </div>

        <!-- Link Management -->
        <div>
            <div class="flex flex-col md:flex-row justify-between md:items-center mb-4">
                <h2 class="text-2xl font-bold">我的链接</h2>
                <div class="flex space-x-2 mt-4 md:mt-0">
                    <button onclick="openAddModal()" class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm font-medium hover:bg-primary/90">+ 新建链接</button>
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="delete_expired">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <button type="submit" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md text-sm font-medium hover:bg-destructive/90" onclick="return confirm('确定删除所有已过期链接?');">删除过期链接</button>
                    </form>
                    <select onchange="window.location.href='?sort='+this.value" class="px-3 py-2 border border-input rounded-md text-sm bg-card">
                        <option value="time" <?php if($sort === 'time') echo 'selected'; ?>>按时间排序</option>
                        <option value="clicks" <?php if($sort === 'clicks') echo 'selected'; ?>>按点击量排序</option>
                    </select>
                </div>
            </div>

            <!-- Mobile Link Cards -->
            <div class="md:hidden space-y-4">
                <?php foreach ($links as $link): ?>
                    <div class="bg-card rounded-lg border p-4 space-y-3">
                        <div class="flex justify-between items-start">
                            <div>
                                <a href="<?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?>" target="_blank" class="text-sm font-semibold text-primary hover:underline"><?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?></a>
                                <p class="text-xs text-muted-foreground truncate" title="<?php echo htmlspecialchars($link['longurl']); ?>"><?php echo htmlspecialchars(mb_strimwidth($link['longurl'], 0, 54, '...')); ?></p>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="openEditModal('<?php echo htmlspecialchars($link['shortcode']); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl'])); ?>', <?php echo $link['enable_intermediate_page'] ? 'true' : 'false'; ?>, <?php echo $link['redirect_delay']; ?>, '<?php echo $link['link_password'] ? '***' : ''; ?>', '<?php echo $link['expiration_date'] ? htmlspecialchars($link['expiration_date']) : ''; ?>')" class="p-1.5 text-muted-foreground hover:text-foreground"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg></button>
                                <form method="post" class="inline" onsubmit="return confirm('删除?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                    <button type="submit" class="p-1.5 text-destructive hover:text-destructive/80"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                                </form>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                            <span>点击: <?php echo $link['clicks']; ?></span>
                            <span>创建于: <?php echo date('y-m-d', strtotime($link['created_at'])); ?></span>
                            <button onclick="copyToClipboardText('<?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?>')" class="px-2 py-1 bg-secondary text-secondary-foreground rounded">复制</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($links)): ?>
                    <div class="text-center py-12 text-muted-foreground">暂无链接，快去创建一个吧！</div>
                <?php endif; ?>
            </div>

            <!-- Desktop Link Table -->
            <div class="hidden md:block overflow-x-auto">
                <div class="inline-block min-w-full align-middle">
                    <div class="overflow-hidden border border-border rounded-lg">
                        <table class="min-w-full divide-y divide-border">
                            <thead class="bg-card">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">短链接</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">长链接</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">点击</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">创建日期</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">操作</th>
                                </tr>
                            </thead>
                            <tbody class="bg-card divide-y divide-border">
                                <?php foreach ($links as $link): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-foreground"><?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><div class="truncate max-w-xs" title="<?php echo htmlspecialchars($link['longurl']); ?>"><?php echo htmlspecialchars($link['longurl']); ?></div></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['clicks']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo date('Y-m-d', strtotime($link['created_at'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button onclick="copyToClipboardText('<?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?>')" class="text-primary hover:underline">复制</button>
                                            <button onclick="openEditModal('<?php echo htmlspecialchars($link['shortcode']); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl'])); ?>', <?php echo $link['enable_intermediate_page'] ? 'true' : 'false'; ?>, <?php echo $link['redirect_delay']; ?>, '<?php echo $link['link_password'] ? '***' : ''; ?>', '<?php echo $link['expiration_date'] ? htmlspecialchars($link['expiration_date']) : ''; ?>')" class="text-primary hover:underline">编辑</button>
                                            <form method="post" class="inline" onsubmit="return confirm('确定删除?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                                <button type="submit" class="text-destructive hover:underline">删除</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($links)): ?>
                                    <tr><td colspan="5" class="px-6 py-12 text-center text-muted-foreground">暂无链接.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>

    <!-- Modals -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4">创建新链接</h3>
            <form method="post" id="addForm" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div>
                    <label class="text-sm font-medium mb-2 block">长链接</label>
                    <input type="url" name="url" class="w-full px-3 py-2 border border-input rounded-md bg-transparent" placeholder="https://example.com" required>
                </div>
                <div>
                    <label class="text-sm font-medium mb-2 block">自定义短码（可选）</label>
                    <input type="text" name="custom_code" class="w-full px-3 py-2 border border-input rounded-md bg-transparent" placeholder="自定义短码" maxlength="10">
                </div>
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium">启用中继页</label>
                    <label class="switch"><input type="checkbox" name="enable_intermediate"><span class="slider"></span></label>
                </div>
                <div>
                    <label class="text-sm font-medium mb-2 block">跳转延迟（秒）</label>
                    <input type="number" name="redirect_delay" class="w-full px-3 py-2 border border-input rounded-md bg-transparent" placeholder="跳转延迟（秒）" min="0" value="0">
                </div>
                <div>
                    <label class="text-sm font-medium mb-2 block">链接密码（可选）</label>
                    <input type="password" name="link_password" class="w-full px-3 py-2 border border-input rounded-md bg-transparent" placeholder="链接密码（可选）">
                </div>
                <div>
                    <label class="text-sm font-medium mb-2 block">过期日期（可选）</label>
                    <input type="date" name="expiration" class="w-full px-3 py-2 border border-input rounded-md bg-transparent">
                </div>
                <?php if (get_setting($pdo, 'turnstile_enabled') === 'true'): ?>
                <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(get_setting($pdo, 'turnstile_site_key')); ?>"></div>
                <?php endif; ?>
                <div class="flex gap-2">
                    <button type="button" onclick="closeAddModal()" class="flex-1 px-4 py-2 rounded-md border hover:bg-accent">取消</button>
                    <button type="submit" class="flex-1 px-4 py-2 rounded-md bg-primary text-primary-foreground hover:bg-primary/90">创建</button>
                </div>
            </form>
        </div>
    </div>
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4">编辑链接</h3>
            <form method="post" id="editForm" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="code" id="editCode">
                <div>
                    <label class="text-sm font-medium mb-2 block">长链接</label>
                    <input type="url" name="newurl" id="editUrl" class="w-full px-3 py-2 border border-input rounded-md bg-transparent" required>
                </div>
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium">启用中继页</label>
                    <label class="switch"><input type="checkbox" name="enable_intermediate" id="editIntermediate"><span class="slider"></span></label>
                </div>
                <div>
                    <label class="text-sm font-medium mb-2 block">跳转延迟（秒）</label>
                    <input type="number" name="redirect_delay" id="editDelay" class="w-full px-3 py-2 border border-input rounded-md bg-transparent" min="0">
                </div>
                <div>
                    <label class="text-sm font-medium mb-2 block">新密码（留空不修改）</label>
                    <input type="password" name="link_password" id="editPassword" class="w-full px-3 py-2 border border-input rounded-md bg-transparent" placeholder="新密码（留空不修改）">
                </div>
                <div>
                    <label class="text-sm font-medium mb-2 block">过期日期</label>
                    <input type="date" name="expiration" id="editExpiration" class="w-full px-3 py-2 border border-input rounded-md bg-transparent">
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="closeEditModal()" class="flex-1 px-4 py-2 rounded-md border hover:bg-accent">取消</button>
                    <button type="submit" class="flex-1 px-4 py-2 rounded-md bg-primary text-primary-foreground hover:bg-primary/90">保存</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function openAddModal() { document.getElementById('addModal').classList.remove('hidden'); }
        function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }
        function openEditModal(code, url, enableIntermediate, delay, password, expiration) {
            document.getElementById('editCode').value = code;
            document.getElementById('editUrl').value = url;
            document.getElementById('editIntermediate').checked = enableIntermediate;
            document.getElementById('editDelay').value = delay;
            document.getElementById('editPassword').placeholder = password ? '留空不修改' : '新密码';
            document.getElementById('editExpiration').value = expiration ? expiration.split(' ')[0] : '';
            document.getElementById('editModal').classList.remove('hidden');
        }
        function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }
        function copyToClipboardText(text) {
            navigator.clipboard.writeText(text).then(() => alert('已复制!'));
        }
        // Charts
        const colors = ['#3b82f6', '#ef4444', '#f97316', '#84cc16', '#22c55e', '#14b8a6', '#06b6d4', '#6366f1', '#8b5cf6', '#d946ef'];
        new Chart(document.getElementById('dailyLine'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($daily_clicks)); ?>,
                datasets: [{
                    label: '每日点击量',
                    data: <?php echo json_encode(array_values($daily_clicks)); ?>,
                    borderColor: colors[0],
                    tension: 0.1,
                    fill: false
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
        new Chart(document.getElementById('topSourcesPie'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($sources, 'domain')); ?>,
                datasets: [{
                    label: '来源',
                    data: <?php echo json_encode(array_column($sources, 'count')); ?>,
                    backgroundColor: colors,
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    </script>
</body>
</html>
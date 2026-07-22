<?php
/** Blog: /blog (list), /blog/{slug} (post), /blog/category/{slug}. Server-rendered. */
require_once __DIR__ . '/config/config.php';
checkMaintenance();

$site = getSetting('site_name', 'AK Menu System');
$primary = getSetting('primary_color', '#e63946');
$secondary = getSetting('secondary_color', '#1d3557');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$slug = trim((string)($_GET['slug'] ?? ''));
$cat  = trim((string)($_GET['category'] ?? ''));

// ---- Single post ----
if ($slug !== '') {
    try { $post = db_one('SELECT * FROM ' . tbl('blog_posts') . " WHERE slug=:s AND status='published'", [':s' => $slug]); }
    catch (Throwable $ex) { $post = null; }
    if (!$post) { require __DIR__ . '/404.php'; exit; }
    try { db_query('UPDATE ' . tbl('blog_posts') . ' SET views=views+1 WHERE id=:id', [':id' => (int)$post['id']]); } catch (Throwable $ex) {}
    $canon = absUrl('blog/' . $post['slug']);
    $meta = [
        'title'       => ($post['meta_title'] ?: $post['title']) . " | $site",
        'description' => $post['meta_description'] ?: $post['excerpt'],
        'canonical'   => $canon, 'og_type' => 'article',
        'image'       => $post['featured_image'] ? mediaUrl($post['featured_image']) : null,
    ];
    $ld = [
        ['@context' => 'https://schema.org', '@type' => 'BlogPosting',
         'headline' => $post['title'], 'description' => $post['excerpt'],
         'image' => $post['featured_image'] ? mediaUrl($post['featured_image']) : absUrl('assets/img/food/paneer-tikka.jpg'),
         'author' => ['@type' => 'Organization', 'name' => $post['author'] ?: $site],
         'publisher' => ['@type' => 'Organization', 'name' => $site],
         'datePublished' => $post['published_at'] ? date('c', strtotime($post['published_at'])) : date('c'),
         'dateModified' => $post['updated_at'] ? date('c', strtotime($post['updated_at'])) : date('c'),
         'mainEntityOfPage' => $canon],
        jsonldBreadcrumb([['Home', absUrl('')], ['Blog', absUrl('blog')], [$post['title'], $canon]]),
    ];
    render_landing_head($meta, $primary, $secondary, $site, $ld);
    ?>
    <main class="wrap lp-main" style="max-width:760px">
        <a href="<?= $e(absUrl('blog')) ?>" style="color:var(--muted)">&larr; All articles</a>
        <h1 style="font-size:2rem;margin:.6rem 0"><?= $e($post['title']) ?></h1>
        <div style="color:var(--muted);font-size:.9rem;margin-bottom:1.2rem">
            <?= $post['published_at'] ? $e(date('d M Y', strtotime($post['published_at']))) : '' ?>
            <?= $post['reading_time'] ? ' · ' . (int)$post['reading_time'] . ' min read' : '' ?>
        </div>
        <?php if ($post['featured_image']): ?><img src="<?= $e(mediaUrl($post['featured_image'])) ?>" alt="<?= $e($post['image_alt'] ?: $post['title']) ?>" style="border-radius:14px;margin-bottom:1.2rem" loading="lazy"><?php endif; ?>
        <article class="lp-content"><?= $post['content'] /* trusted admin HTML */ ?></article>
    </main>
    <?php
    render_landing_foot($site);
    exit;
}

// ---- Listing ----
try {
    if ($cat !== '') {
        $catRow = db_one('SELECT * FROM ' . tbl('blog_categories') . ' WHERE slug=:s', [':s' => $cat]);
        $posts = $catRow ? db_all('SELECT * FROM ' . tbl('blog_posts') . " WHERE status='published' AND category_id=:c ORDER BY published_at DESC", [':c' => (int)$catRow['id']]) : [];
    } else {
        $posts = db_all('SELECT * FROM ' . tbl('blog_posts') . " WHERE status='published' ORDER BY published_at DESC LIMIT 50");
    }
} catch (Throwable $ex) { $posts = []; }

$meta = [
    'title'       => "Blog — Digital Menu &amp; Restaurant Tips | $site",
    'description' => "Guides and tips on QR code menus, digital ordering and growing your restaurant in India.",
    'canonical'   => absUrl($cat !== '' ? 'blog/category/' . $cat : 'blog'),
];
render_landing_head($meta, $primary, $secondary, $site);
?>
<header class="lp-hero"><div class="wrap">
    <div class="eyebrow">📝 Blog</div>
    <h1>Restaurant &amp; digital-menu guides</h1>
    <p class="lead">Practical tips on QR menus, online ordering, WhatsApp and growing your restaurant.</p>
</div></header>
<main class="wrap lp-main">
    <?php if (!$posts): ?>
        <p style="color:var(--muted)">Articles are coming soon. Meanwhile, <a href="<?= $e(absUrl('signup.php')) ?>">start your free trial</a>.</p>
    <?php else: foreach ($posts as $p): ?>
        <article style="border-bottom:1px solid var(--line);padding:1.1rem 0">
            <h2 style="margin:0 0 .3rem;font-size:1.3rem"><a href="<?= $e(absUrl('blog/' . $p['slug'])) ?>" style="color:var(--ink)"><?= $e($p['title']) ?></a></h2>
            <div style="color:var(--muted);font-size:.85rem"><?= $p['published_at'] ? $e(date('d M Y', strtotime($p['published_at']))) : '' ?></div>
            <p style="color:var(--muted)"><?= $e($p['excerpt']) ?></p>
            <a href="<?= $e(absUrl('blog/' . $p['slug'])) ?>">Read more &rarr;</a>
        </article>
    <?php endforeach; endif; ?>
</main>
<?php
render_landing_foot($site);

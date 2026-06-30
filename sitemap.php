<?php
include_once "config/connect.php";

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';

$base = rtrim(BASE_URL, '/');
$today = date('Y-m-d');

function loc(string $url): string
{
    return '<loc>' . htmlspecialchars($url, ENT_XML1) . '</loc>';
}

function url(string $l, string $lastmod = '', string $changefreq = 'monthly', string $priority = '0.6'): string
{
    $out  = "\n  <url>\n    " . loc($l);
    if ($lastmod) $out .= "\n    <lastmod>$lastmod</lastmod>";
    $out .= "\n    <changefreq>$changefreq</changefreq>";
    $out .= "\n    <priority>$priority</priority>";
    $out .= "\n  </url>";
    return $out;
}
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
          http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"><?php

/* ── Static pages ── */
echo url("$base/",                       $today, 'weekly',  '1.0');
echo url("$base/about-us.php",           $today, 'monthly', '0.8');
echo url("$base/contact-us.php",         $today, 'monthly', '0.8');
echo url("$base/login.php",              $today, 'monthly', '0.6');
echo url("$base/signup.php",             $today, 'monthly', '0.6');
echo url("$base/doctor-signup.php",      $today, 'monthly', '0.5');
echo url("$base/school-register.php",    $today, 'monthly', '0.6');
echo url("$base/student-register.php",   $today, 'monthly', '0.6');
echo url("$base/teacher-register.php",   $today, 'monthly', '0.6');
echo url("$base/forgot-password.php",    $today, 'monthly', '0.4');
echo url("$base/privacy-policy.php",     $today, 'yearly',  '0.4');
echo url("$base/terms-and-condition.php",$today, 'yearly',  '0.4');
echo url("$base/disclaimer.php",         $today, 'yearly',  '0.4');
echo url("$base/legal-compliance.php",   $today, 'yearly',  '0.4');

/* ── Departments ── */
$res = $conn->query("SELECT slug_url FROM sub_categories WHERE parent_id = 20873 AND status = 1 ORDER BY id");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (empty($row['slug_url'])) continue;
        echo url("$base/department/{$row['slug_url']}", $today, 'monthly', '0.8');
    }
}

/* ── Online services ── */
$res = $conn->query("SELECT slug_url FROM products WHERE pro_cate = 87920 AND status = 1 ORDER BY id");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (empty($row['slug_url'])) continue;
        echo url("$base/online-services/{$row['slug_url']}", $today, 'monthly', '0.7');
    }
}

/* ── Doctor profiles ── */
$res = $conn->query("SELECT slug_url FROM doctors WHERE status = 'Active' AND is_verified = 1 ORDER BY id");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (empty($row['slug_url'])) continue;
        echo url("$base/doctor-profile/{$row['slug_url']}", $today, 'weekly', '0.7');
    }
}

/* ── Blog posts ── */
$res = $conn->query("SELECT slug_url FROM blogs WHERE status = 1 ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (empty($row['slug_url'])) continue;
        echo url("$base/blogs/{$row['slug_url']}", $today, 'weekly', '0.7');
    }
}

?>

</urlset>

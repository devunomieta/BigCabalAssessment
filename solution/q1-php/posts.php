<?php
$page   = max(1, (int) ($_GET['page'] ?? 1));
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$q = new WP_Query([
    'posts_per_page' => 10,
    'paged'          => $page,
    's'              => $search,
]);

$pages = $q->max_num_pages;
?>
<h1>
    Page <?= $page ?> of <?= $pages ?>
    <?php if ($search !== '') : ?>
        : Search for <?= esc_html($search) ?>
    <?php endif; ?>
</h1>

<?php if (! $q->have_posts()) : ?>
    <p>No results found.</p>
<?php endif; ?>

<?php while ($q->have_posts()) : $q->the_post(); ?>
    <?php
    $content = get_the_content();
    $content = preg_replace_callback(
        '/\[ad(?::([a-zA-Z0-9_-]+))?\]/',
        function ($m) {
            $modifier = isset($m[1]) && $m[1] !== '' ? ' ad-' . esc_attr($m[1]) : '';
            return '<div class="ad' . $modifier . '"></div>';
        },
        $content
    );
    ?>
    <h2><?= esc_html(get_the_title()) ?></h2>
    <p><?php echo wp_kses_post($content); ?></p>
<?php endwhile; ?>

<?php wp_reset_postdata(); ?>

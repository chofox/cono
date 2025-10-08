<?php
if (!defined('APP_URL')) {
    throw new RuntimeException('APP_URL must be defined before including page_start.php');
}

$page_title = $page_title ?? '';
$page_subtitle = $page_subtitle ?? '';
$document_title_parts = [];
if ($page_title !== '') {
    $document_title_parts[] = $page_title;
}
$document_title_parts[] = APP_NAME;
$document_title = implode(' - ', $document_title_parts);

$extra_head_styles = $extra_head_styles ?? [];
$extra_head_scripts = $extra_head_scripts ?? [];
$body_class = $body_class ?? '';
$breadcrumbs = $breadcrumbs ?? [];
$page_actions = $page_actions ?? [];

if (!function_exists('render_breadcrumbs')) {
    function render_breadcrumbs(array $breadcrumbs): string {
        if (empty($breadcrumbs)) {
            return '';
        }

        $items = [];
        $last_index = array_key_last($breadcrumbs);
        foreach ($breadcrumbs as $index => $breadcrumb) {
            $label = $breadcrumb['label'] ?? '';
            $href = $breadcrumb['href'] ?? '';
            $escaped_label = escape_html($label);

            if ($index === $last_index) {
                $items[] = sprintf('<li class="is-active"><a aria-current="page">%s</a></li>', $escaped_label);
            } elseif ($href !== '') {
                $items[] = sprintf('<li><a href="%s">%s</a></li>', htmlspecialchars($href, ENT_QUOTES, 'UTF-8'), $escaped_label);
            } else {
                $items[] = sprintf('<li><a>%s</a></li>', $escaped_label);
            }
        }

        return '<nav class="breadcrumb is-small mb-4" aria-label="breadcrumbs"><ul>' . implode('', $items) . '</ul></nav>';
    }
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_html($document_title); ?></title>
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/img/favicon.svg" type="image/svg+xml">
    <?php foreach ($extra_head_styles as $style): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($style, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <?php foreach ($extra_head_scripts as $script): ?>
        <script src="<?php echo htmlspecialchars($script, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <?php endforeach; ?>
</head>
<body class="<?php echo htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8'); ?>">
<?php include_once __DIR__ . '/navbar.php'; ?>
<main class="section app-shell">
    <div class="container">
        <div class="columns is-variable is-5">
            <div class="column is-12-tablet is-4-desktop is-3-widescreen">
                <?php include __DIR__ . '/sidebar.php'; ?>
            </div>
            <div class="column is-12-tablet is-8-desktop is-9-widescreen app-content">
                <?php echo render_breadcrumbs($breadcrumbs); ?>
                <?php if ($page_title !== '' || $page_subtitle !== '' || !empty($page_actions)): ?>
                    <div class="section-header">
                        <div>
                            <?php if ($page_title !== ''): ?>
                                <h1 class="title is-3"><?php echo escape_html($page_title); ?></h1>
                            <?php endif; ?>
                            <?php if ($page_subtitle !== ''): ?>
                                <p class="subtitle is-6"><?php echo escape_html($page_subtitle); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($page_actions)): ?>
                            <div class="buttons">
                                <?php foreach ($page_actions as $action): ?>
                                    <?php
                                        $label = $action['label'] ?? '';
                                        $href = $action['href'] ?? '#';
                                        $icon = $action['icon'] ?? '';
                                        $class = $action['class'] ?? 'button is-primary';
                                        $target = $action['target'] ?? '';
                                        $rel = $action['rel'] ?? '';
                                        $attributes = '';
                                        if ($target !== '') {
                                            $attributes .= ' target="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"';
                                        }
                                        if ($rel !== '') {
                                            $attributes .= ' rel="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '"';
                                        }
                                    ?>
                                    <a class="<?php echo htmlspecialchars($class, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $attributes; ?>>
                                        <?php if ($icon !== ''): ?>
                                            <span class="icon"><i class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                                        <?php endif; ?>
                                        <span><?php echo escape_html($label); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php show_flash_message(); ?>

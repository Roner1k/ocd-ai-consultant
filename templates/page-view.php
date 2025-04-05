<?php
global $wpdb;

// List of selectable tables
$tables = [
    'ocd_ai_knowledge_base' => 'KB Import Data',
    'ocd_ai_model_log' => 'Model Generation Log',
];

$selected = sanitize_text_field($_GET['table'] ?? 'ocd_ai_knowledge_base');
$table_name = $wpdb->prefix . $selected;

// Pagination
$per_page = 100;
$paged = max(1, intval($_GET['paged'] ?? 1));
$offset = ($paged - 1) * $per_page;

// Fetch data
//$data = $wpdb->get_results("SELECT * FROM `$table_name` LIMIT $per_page OFFSET $offset", ARRAY_A);
$data = $wpdb->get_results("SELECT * FROM `$table_name` ORDER BY id DESC LIMIT $per_page OFFSET $offset", ARRAY_A);

$total = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");

// Get column names
$columns = [];
if (!empty($data)) {
    $columns = array_keys($data[0]);
}
?>

<div class="wrap">
    <h1>Data Explorer</h1>

    <h2 class="nav-tab-wrapper">
        <?php foreach ($tables as $slug => $label): ?>
            <a href="?page=ocd-ai-view&table=<?php echo esc_attr($slug); ?>"
               class="nav-tab <?php echo ($slug === $selected) ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <?php if (!empty($columns)): ?>
        <table class="widefat striped">
            <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th><?php echo esc_html($col); ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $row): ?>
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <td><?php echo esc_html($row[$col]); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        // Pagination links
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $url = add_query_arg(['paged' => $i, 'table' => $selected], admin_url('admin.php?page=ocd-ai-view'));
                echo '<a class="page-numbers' . ($i === $paged ? ' current' : '') . '" href="' . esc_url($url) . '">' . $i . '</a> ';
            }
            echo '</div></div>';
        }
        ?>

    <?php else: ?>
        <p>No data found in this table.</p>
    <?php endif; ?>
</div>

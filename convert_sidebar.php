<?php
$conversions = [
    // Donor pages
    'modules/donor/dashboard.php' => [
        'header_block' => '<div class="header">',
        'header_end' => '</div>',
        'body_tag' => '<body>',
        'container_tag' => '<div class="dashboard-container">',
        'container_end' => '</div>',
    ],
    'modules/donor/donation_history.php' => [
        'header_block' => '<div class="header">',
        'header_end' => '</div>',
        'body_tag' => '<body>',
        'container_tag' => '<div class="container">',
        'container_end' => '</div>',
    ],
    'modules/donor/register.php' => [
        'header_block' => null, // No header, simple page
        'header_end' => null,
        'body_tag' => '<body>',
        'container_tag' => null,
        'container_end' => null,
    ],
    // Recipient pages
    'modules/recipient/dashboard.php' => [
        'header_block' => '<div class="header">',
        'header_end' => '</div>',
        'body_tag' => '<body>',
        'container_tag' => '<div class="dashboard-container">',
        'container_end' => '</div>',
    ],
    'modules/recipient/register.php' => [
        'header_block' => null,
        'header_end' => null,
        'body_tag' => '<body>',
        'container_tag' => null,
        'container_end' => null,
    ],
    'modules/recipient/request.php' => [
        'header_block' => null,
        'header_end' => null,
        'body_tag' => '<body>',
        'container_tag' => null,
        'container_end' => null,
    ],
    'modules/recipient/track_request.php' => [
        'header_block' => null,
        'header_end' => null,
        'body_tag' => '<body>',
        'container_tag' => null,
        'container_end' => null,
    ],
];

foreach ($conversions as $file => $config) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        echo "Skipping $file (not found)\n";
        continue;
    }
    
    $content = file_get_contents($path);
    
    // Add style.css link if missing
    if (strpos($content, 'style.css') === false) {
        $content = str_replace('</head>', '    <link rel="stylesheet" href="../../assets/css/style.css">' . "\n</head>", $content);
    }
    
    // Replace body tag
    $content = str_replace($config['body_tag'], '<body class="has-sidebar">', $content);
    
    // Add sidebar include after body tag
    $sidebarInclude = "    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>\n    <div class=\"main-content\">\n";
    $content = str_replace('<body class="has-sidebar">', '<body class="has-sidebar">' . "\n" . $sidebarInclude, $content);
    
    // Remove old header block if exists
    if ($config['header_block']) {
        $headerStart = strpos($content, $config['header_block']);
        $headerEnd = strpos($content, $config['header_end'], $headerStart) + strlen($config['header_end']);
        if ($headerStart !== false && $headerEnd !== false) {
            $content = substr($content, 0, $headerStart) . substr($content, $headerEnd);
        }
    }
    
    // Wrap container if it exists, otherwise wrap body content
    if ($config['container_tag']) {
        // Find and wrap the container
        $containerStart = strpos($content, $config['container_tag']);
        if ($containerStart !== false) {
            // Insert main-content div before container
            $content = substr($content, 0, $containerStart) . '    <div class="main-content">' . "\n    " . substr($content, $containerStart);
        }
    }
    
    // Close main-content div before </body>
    $content = str_replace('</body>', '    </div>' . "\n</body>", $content);
    
    file_put_contents($path, $content);
    echo "Converted $file\n";
}

echo "\nAll conversions complete!\n";
?>

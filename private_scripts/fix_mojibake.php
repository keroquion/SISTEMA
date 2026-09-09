<?php
$dir = __DIR__;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

$replacements = [
    "á" => "á",
    "é" => "é",
    "ó" => "ó",
    "ñ" => "ñ",
    "í" => "í",
    "🏦" => "🏦",
    "🎁" => "🎁",
    "🎉" => "🎉",
    "👨‍🔧" => "👨‍🔧",
    "👤" => "👤",
    "💾" => "💾",
    "✅" => "✅",
    "📦" => "📦",
    "💬" => "💬",
    "💻" => "💻",
    "🛒" => "🛒",
    "📄" => "📄",
    "📖" => "📖",
    "👨‍💻" => "👨‍💻",
    "👑" => "👑",
    "🌐" => "🌐",
    "🔍" => "🔍",
    "🛠️" => "🛠️",
    "🟢" => "🟢",
    "🚀" => "🚀",
    "🔧" => "🔧",
    "📋" => "📋",
    "🙌" => "🙌"
];

$count = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['html', 'php', 'js'])) {
        $content = file_get_contents($file->getPathname());
        $original = $content;
        
        foreach($replacements as $broken => $fixed) {
            $content = str_replace($broken, $fixed, $content);
        }
        
        if ($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            echo "Fixed: " . $file->getFilename() . "<br>\n";
            $count++;
        }
    }
}
echo "Done! Fixed $count files.";
?>

<?php
$path = 'D:/Program Marzuki/Package Projek Laravel/tools/storage/app/public/meta-ads/5/base.webp';
echo "File exists: " . (file_exists($path) ? 'yes' : 'no') . "\n";
echo "File size: " . filesize($path) . " bytes\n";
$info = @getimagesize($path);
if ($info) {
    echo "Image type: " . $info['mime'] . "\n";
    echo "Width: " . $info[0] . ", Height: " . $info[1] . "\n";
    $img = @imagecreatefromwebp($path);
    if ($img) {
        echo "GD: OK\n";
        imagedestroy($img);
    } else {
        echo "GD: FAILED to create image\n";
    }
} else {
    echo "getimagesize: FAILED\n";
}

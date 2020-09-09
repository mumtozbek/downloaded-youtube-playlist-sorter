<?php
// Definitions
require_once ('config.php');

// Functions
function getFiles($path) {
  $results = array();

  $directory = new \RecursiveDirectoryIterator($path);
  $iterator = new \RecursiveIteratorIterator($directory);

  foreach ($iterator as $info) {
    if (in_array(strtolower(pathinfo($info->getFileName(), PATHINFO_EXTENSION)), array('mp4','webm')) && !preg_match('#.*_a\.(mp4|webm)$#', $info->getFileName()) && !preg_match('#.*\.full\.(mp4|webm)$#', $info->getFileName())) {
      $results[] = $info->getPathname();
    }
  }

  return $results;
}

if ($argc > 1) {
  if ($argv[1] == 'merge') {
    // Merge
    $files = getFiles(PATH);
    foreach ($files as $video) {
      $full = preg_replace('#\.(mp4|webm)$#', '.full.${1}', $video);
      $audio = preg_replace('#\.(mp4|webm)#', '_a.${1}', $video);
      if (!is_file($full) && is_file($audio)) {
        exec(FFMPEG_PATH . ' -y -i "' . $video . '" -i "' . $audio . '" -c copy "' . $full . '"', $output, $result);
        echo '"' . basename($video) . '" merged with "' . basename($audio) . '"' . "\n";
      }
    }
  } elseif ($argv[1] == 'clean') {
    // Clean
    $files = getFiles(PATH);
    foreach ($files as $video) {
      $full = preg_replace('#\.(mp4|webm)$#', '.full.${1}', $video);
      $audio = preg_replace('#\.(mp4|webm)#', '_a.${1}', $video);
      if (is_file($full) && filesize($full) < (filesize($video) + filesize($audio))) {
        unlink($full);
      }
      if (is_file($full)) {
        if (is_file($video)) {
          unlink($video);
        }
        if (is_file($audio)) {
          unlink($audio);
        }
        rename($full, $video);
      }
    }
  }
}
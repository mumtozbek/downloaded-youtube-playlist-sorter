<?php
// Definitions
require_once ('config.php');

// Create function mb_ucfirst, if mb extension is enabled
if (!function_exists('mb_ucfirst')) {
  function mb_ucfirst($string, $enc = 'UTF-8') {
    return mb_strtoupper(mb_substr($string, 0, 1, $enc), $enc) . mb_substr($string, 1, mb_strlen($string, $enc), $enc);
  }
}

// Functions
function getMetaFiles($path) {
  $items = [];

  $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
  foreach ($iterator as $info) {
    if (preg_match('#.*' . preg_quote(' - YouTube - YouTube Playlist') . '$#', $info->getFileName()) || in_array(strtolower(pathinfo($info->getFileName(), PATHINFO_EXTENSION)), ['youtube'])) {
      $items[] = $info->getPathname();
    }
  }

  return $items;
}

function getPlayListTitle($listId) {
  $list = json_decode(file_get_contents('https://www.googleapis.com/youtube/v3/playlists?part=snippet&id=' . $listId . '&key=' . API_KEY), true);
  if ($list && empty($list['error'])) {
    return $list['items'][0]['snippet']['title'];
  }
}

function getPlayListItems($listId, $limit = 50, $pageToken = '') {
  $items = [];
  $list = json_decode(file_get_contents('https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=' . $limit . ($pageToken ? '&pageToken=' . $pageToken : '') . '&playlistId=' . $listId . '&key=' . API_KEY), true);
  if ($list && empty($list['error'])) {
    foreach ($list['items'] as $item) {
      $items[] = ['id' => $item['snippet']['resourceId']['videoId'], 'title' => $item['snippet']['title']];
    }
    if (!empty($list['nextPageToken'])) {
      $items = array_merge($items, getPlayListItems($listId, $limit, $list['nextPageToken']));
    }
  }
  return $items;
}

// Indexing
$metaFiles = getMetaFiles(PATH);
foreach ($metaFiles as $metaFile) {
  if (preg_match('#.*' . preg_quote(' - YouTube - YouTube Playlist') . '$#', $metaFile)) {
    $content = file_get_contents($metaFile);
    $content = str_replace('&', '&amp;', $content);
    $content = iconv(mb_detect_encoding($content, mb_detect_order(), true), "UTF-8", $content);
    $xml = simplexml_load_string($content);

    // Parse items
    $items = [];
    foreach ($xml->URL as $url) {
      $params = parse_url((string)$url->HREF);
      parse_str($params['query'], $args);
      if ((string)$url->COMMENT != 'Private video') {
        $items[] = ['id' => $args['v'], 'title' => (string)$url->COMMENT];
      }
    }

    // Parse items
    if ($items) {
      $index = 1;
      foreach ($items as $item) {
        $found = false;

        // DM video title
        $dmTitle = preg_replace('#\s+#', ' ', preg_replace('#[/\?\<\>\:\*\"]#', '', $item['title']));
        $dmTitle = preg_replace('#[|/]#', '', $dmTitle);
        $dmTitle = str_replace(['и?'], ['ии'], $dmTitle);
        //

        // Correct video title
        $correctTitle = preg_replace('#\s+#', ' ', preg_replace('#[|//\?\<\>\:\*]#', ' - ', preg_replace('#[\"]#', '', trim($item['title']))));
        $correctTitle = preg_replace('#^\#?\d{1,2}\.? #', '', $correctTitle);
        $correctTitle = preg_replace('#^\#?\d{3,3}\. #', '', $correctTitle);
        $correctTitle = preg_replace('# - (\w+)?$#', '', $correctTitle);
        $correctTitle = str_replace(['—', 'и?'], ['-', 'ий'], $correctTitle);
        $correctTitle = trim($correctTitle);

        // Video existance check
        if ($videos = glob(dirname($metaFile) . '/' . $dmTitle . '{ - ' . $index . ',}.{webm,mp4}', GLOB_BRACE)) {
          $found = end($videos);
        } elseif ($videos = glob(dirname($metaFile) . '/' . sprintf("%03d", $index) . '. ' . $correctTitle . '.{webm,mp4}', GLOB_BRACE)) {
          $found = end($videos);
        } else {
          $videos = glob(dirname($metaFile) . '/*.{webm,mp4}', GLOB_BRACE);
          foreach ($videos as $video) {
            if (preg_replace('#^(\d{3}\. )?(.*)(\.webm|\.mp4)$#', '${2}', preg_replace('#( - youtube)\.#', '.', mb_strtolower(basename($video)))) == mb_strtolower($dmTitle) || preg_replace('#^(\d{3}\. )?(.*)(\.webm|\.mp4)$#', '${2}', mb_strtolower(basename($video))) == mb_strtolower($correctTitle)) {
              $found = $video;
              break;
            }
          }
        }

        // Rename files
        if ($found) {
          $fileName = sprintf("%03d", $index) . '. ' . mb_ucfirst($correctTitle);
          if (preg_replace('#[/\/]#', DIRECTORY_SEPARATOR, $found) != preg_replace('#[/\/]#', DIRECTORY_SEPARATOR, dirname($found) . '/' . $fileName . '.' . pathinfo($found, PATHINFO_EXTENSION))) {
            if (isset($argv[1]) && $argv[1] == 'execute') {
              rename($found, dirname($found) . '/' . $fileName . '.' . pathinfo($found, PATHINFO_EXTENSION));
              echo $index . '. "' . basename($found) . '" -> "' . basename($fileName) . '.' . pathinfo($found, PATHINFO_EXTENSION) . '"' . "\n";
            }
          }
          $index++;
        } else {
          echo '"' . $dmTitle . '"' . " is not found!!!\n";
          file_put_contents(__DIR__ . '/log.txt', '"' . $dmTitle . '"' . " is not found!!!\n", FILE_APPEND);
        }
      }
    }

    // Finish
    if (isset($argv[1]) && $argv[1] == 'execute') {
      // Hide meta file
      exec('attrib +h "' . realpath($metaFile) . '"');

      // Rename folder
      $listTitle = preg_replace('#(\.)?' . preg_quote(' - YouTube - YouTube Playlist') . '$#', '', basename($metaFile));
      $listTitle = preg_replace('#\s+#', ' ', preg_replace('#[\//\?\<\>\:\*]#', ' - ', preg_replace('#[\"]#', '', $listTitle)));
      if (!preg_match('# \(\d+\)$#', basename(dirname($metaFile)))) {
        rename(dirname($metaFile), dirname(dirname($metaFile)) . '/' . $listTitle . ' (' . basename(dirname($metaFile)) . ')');
      }
    }
  } elseif (in_array(strtolower(pathinfo($metaFile, PATHINFO_EXTENSION)), ['youtube'])) {
    continue;
    // Extract listId
    $url = trim(file_get_contents($metaFile));
    $params = parse_url($url);
    if (isset($params['query'])) {
      parse_str($params['query'], $args);
      if (isset($args['list'])) {
        $listId = $args['list'];
      } else {
        continue;
      }
    } elseif (isset($params['path'])) {
      $listId = $params['path'];
    }

    // Parse items
    if ($items) {
      $index = 1;
      foreach ($items as $item) {
        $found = false;

        // DM video title
        $dmTitle = preg_replace('#\s+#', ' ', preg_replace('#[/\?\<\>\:\*\"]#', '', $item['title']));
        $dmTitle = preg_replace('#[/]#', '', $dmTitle);

        // Correct video title
        $correctTitle = preg_replace('#\s+#', ' ', preg_replace('#[\//\?\<\>\:\*]#', ' - ', preg_replace('#[\"]#', '', trim($item['title']))));
        $correctTitle = preg_replace('#^\#?\d{1,2}\.? #', '', $correctTitle);
        $correctTitle = preg_replace('#^\#?\d{3,3}\. #', '', $correctTitle);
        $correctTitle = preg_replace('# - (\w+)?$#', '', $correctTitle);
        $correctTitle = trim($correctTitle);

        // Video existance check
        if ($videos = glob(dirname($metaFile) . '/' . $dmTitle . '{ - ' . $index . ',}.{webm,mp4}', GLOB_BRACE)) {
          $found = end($videos);
        } elseif ($videos = glob(dirname($metaFile) . '/' . sprintf("%03d", $index) . '. ' . $correctTitle . '.{webm,mp4}', GLOB_BRACE)) {
          $found = end($videos);
        } else {
          $videos = glob(dirname($metaFile) . '/*.{webm,mp4}', GLOB_BRACE);
          foreach ($videos as $video) {
            if (preg_replace('#^(\d{3}\. )?(.*)(\.webm|\.mp4)$#', '${2}', preg_replace('#( - youtube)\.#', '.', mb_strtolower(basename($video)))) == mb_strtolower($dmTitle) || preg_replace('#^(\d{3}\. )?(.*)(\.webm|\.mp4)$#', '${2}', mb_strtolower(basename($video))) == mb_strtolower($correctTitle)) {
              $found = $video;
              break;
            }
          }
        }

        // Rename files
        if ($found) {
          $fileName = sprintf("%03d", $index) . '. ' . mb_ucfirst($correctTitle);
          if (preg_replace('#[/\/]#', DIRECTORY_SEPARATOR, $found) != preg_replace('#[/\/]#', DIRECTORY_SEPARATOR, dirname($found) . '/' . $fileName . '.' . pathinfo($found, PATHINFO_EXTENSION))) {
            if (isset($argv[1]) && $argv[1] == 'execute') {
              rename($found, dirname($found) . '/' . $fileName . '.' . pathinfo($found, PATHINFO_EXTENSION));
              echo $index . '. "' . basename($found) . '" -> "' . basename($fileName) . '.' . pathinfo($found, PATHINFO_EXTENSION) . '"' . "\n";
            }
          }
          $index++;
        } else {
          echo '"' . $dmTitle . '"' . " is not found!!!\n";
        }
      }
    }

    // Finish
    if (isset($argv[1]) && $argv[1] == 'execute') {
      // Hide meta file
      exec('attrib +h "' . realpath($metaFile) . '"');

      // Rename folder
      $listTitle = preg_replace('#(\.)?' . preg_quote(' - YouTube - YouTube Playlist') . '$#', '', basename($metaFile));
      $listTitle = preg_replace('#\s+#', ' ', preg_replace('#[\//\?\<\>\:\*]#', ' - ', preg_replace('#[\"]#', '', $listTitle)));
      if (!preg_match('# \(\d+\)$#', basename(dirname($metaFile)))) {
        rename(dirname($metaFile), dirname(dirname($metaFile)) . '/' . $listTitle . ' (' . basename(dirname($metaFile)) . ')');
      }
    }
  }
}

//exec('dmaster.exe https://youtube.com/watch?v=' . $item['id'] . ' hidden=1 start=0 savepath="' . preg_replace('#[/\/]#', DIRECTORY_SEPARATOR, dirname($metaFile)) . DIRECTORY_SEPARATOR . '"" filename="' . $correctTitle . '.webm"');
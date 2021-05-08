<?php
namespace App\Component;

class Pagination {
  const PAGES_TO_PREV = 5;
  const PAGES_TO_NEXT = 5;
  public static function get(string $url, int $p, int $last): array {
    $first = 1;
    $pages = [];
    for ($i = $p, $min = max(1, $p - static::PAGES_TO_PREV); $i >= $min; $i--) {
      $pages[] = $i;
    }
    for ($i = $p, $max = min($last, $p + static::PAGES_TO_NEXT); $i <= $max; $i++) {
      $pages[] = $i;
    }
    $pages = array_unique($pages);
    sort($pages);
    return [
      'url' => $url,
      'first' => $first,
      'last' => $last,
      'no_first' => !in_array(1, $pages),
      'no_last' => !in_array($last, $pages),
      'pages' => $pages,
    ];
  }
}
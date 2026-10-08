<?php
declare(strict_types=1);

/**
 * Translation checker.
 *   php tests/i18n.php          check src/lang/*.php against every key used in the code (exit 1 on problems)
 *   php tests/i18n.php --list   print every key used in the code
 *
 * Keys are found in t()/te()/th() calls in PHP (including both branches of a ternary), in tr() calls in the
 * JavaScript, and in the label tables that are translated dynamically (views, presets, export columns, ...).
 */

define('TT_ROOT', dirname(__DIR__));
const TT_VERSION = 'test';
require TT_ROOT . '/src/helpers.php';
spl_autoload_register(static function (string $c): void {
    $f = TT_ROOT . '/src/' . str_replace('\\', '/', substr($c, strlen('TimeTracker\\'))) . '.php';
    if (str_starts_with($c, 'TimeTracker\\') && is_file($f)) {
        require $f;
    }
});

use TimeTracker\Calendar;
use TimeTracker\Export\ReportBuilder;
use TimeTracker\I18n;
use TimeTracker\Repository\Actions;

/** First argument of the call that opens at $open (the "(" position), split at the top-level comma. */
function first_argument(string $src, int $open): string
{
    $depth = 0;
    $quote = null;
    for ($i = $open, $n = strlen($src); $i < $n; $i++) {
        $c = $src[$i];
        if ($quote !== null) {
            if ($c === '\\') {
                $i++;
            } elseif ($c === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($c === "'" || $c === '"') {
            $quote = $c;
        } elseif ($c === '(' || $c === '[') {
            $depth++;
        } elseif ($c === ')' || $c === ']') {
            if (--$depth === 0) {
                return substr($src, $open + 1, $i - $open - 1);
            }
        } elseif ($c === ',' && $depth === 1) {
            return substr($src, $open + 1, $i - $open - 1);
        }
    }
    return '';
}

/** @return string[] unescaped string literals inside an argument expression */
function literals(string $arg, bool $js = false): array
{
    $arg = preg_replace('/\[\s*\'[^\']*\'\s*\]/', '', $arg) ?? $arg; // $row['key'] array indexes are not texts
    $out = [];
    if (preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"/s', $arg, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            if (($x[1] ?? '') !== '' || ($x[0][0] ?? '') === "'") {
                $out[] = str_replace(["\\'", '\\\\'], ["'", '\\'], $x[1]);
            } else {
                $out[] = str_replace(['\\"', '\\\\'], ['"', '\\'], $x[2] ?? '');
            }
        }
    }
    return array_values(array_filter($out, static fn($s) => $s !== ''));
}

function scan(array $files, string $callRegex, bool $js): array
{
    $keys = [];
    foreach ($files as $file) {
        $src = (string) file_get_contents($file);
        if (preg_match_all($callRegex, $src, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$text, $pos]) {
                foreach (literals(first_argument($src, $pos + strlen($text) - 1), $js) as $k) {
                    $keys[$k][] = str_replace(TT_ROOT . '/', '', $file);
                }
            }
        }
    }
    return $keys;
}

$php = array_merge(
    glob(TT_ROOT . '/src/*.php') ?: [], glob(TT_ROOT . '/src/*/*.php') ?: [],
    glob(TT_ROOT . '/public/*.php') ?: [], glob(TT_ROOT . '/public/api/*.php') ?: [],
    glob(TT_ROOT . '/views/*.php') ?: [], glob(TT_ROOT . '/views/*/*.php') ?: []
);
$php = array_filter($php, static fn($f) => !str_ends_with($f, 'I18n.php') || true);
$js = glob(TT_ROOT . '/public/assets/js/*.js') ?: [];

$used = scan($php, '/(?<![\w>$:])(?:t|te|th)\(/', false);
$jsUsed = scan($js, '/(?<![\w.])tr\(/', true);

// Label tables that are translated at display time
$dynamic = [];
foreach (Calendar::VIEWS as $label) {
    $dynamic[$label] = 'Calendar::VIEWS';
}
foreach (array_keys(Calendar::presets()) as $label) {
    $dynamic[$label] = 'Calendar::presets';
}
foreach (ReportBuilder::COLUMNS as [$label]) {
    $dynamic[$label] = 'ReportBuilder::COLUMNS';
}
foreach (ReportBuilder::FORMATS as $label) {
    $dynamic[$label] = 'ReportBuilder::FORMATS';
}
foreach (I18n::JS_KEYS as $label) {
    $dynamic[$label] = 'I18n::JS_KEYS';
}
foreach (Actions::STANDARD as [$name]) {
    $dynamic['action:' . $name] = 'Actions::STANDARD';
}

$all = [];
foreach ([$used, $jsUsed] as $set) {
    foreach ($set as $k => $where) {
        $all[$k] = array_unique(array_merge($all[$k] ?? [], $where));
    }
}
foreach ($dynamic as $k => $where) {
    $all[$k] = array_unique(array_merge($all[$k] ?? [], [$where]));
}
ksort($all);

if (in_array('--list', $argv, true)) {
    foreach ($all as $k => $where) {
        echo $k, "\n";
    }
    fwrite(STDERR, count($all) . " keys\n");
    exit(0);
}

// JS keys must be shipped to the browser
$problems = 0;
foreach ($jsUsed as $k => $where) {
    if (!in_array($k, I18n::JS_KEYS, true)) {
        echo "NOT IN I18n::JS_KEYS (the browser will not receive it): $k\n";
        $problems++;
    }
}

$ph = static function (string $s): array {
    preg_match_all('/\{(\w+)\}/', $s, $m);
    $p = $m[1];
    sort($p);
    return $p;
};
foreach (glob(TT_ROOT . '/src/lang/*.php') ?: [] as $file) {
    $lang = basename($file, '.php');
    $dict = (array) require $file;
    $missing = array_diff_key($all, $dict);
    foreach (array_keys($missing) as $k) {
        echo "[$lang] MISSING: $k   (" . implode(', ', $all[$k]) . ")\n";
        $problems++;
    }
    foreach ($dict as $k => $v) {
        if (!isset($all[$k])) {
            echo "[$lang] unused entry: $k\n"; // warning only
            continue;
        }
        if (!str_starts_with($k, 'action:') && $ph($k) !== $ph((string) $v)) {
            echo "[$lang] PLACEHOLDER MISMATCH: $k => $v\n";
            $problems++;
        }
        if (trim((string) $v) === '') {
            echo "[$lang] EMPTY: $k\n";
            $problems++;
        }
    }
    echo "[$lang] " . count($dict) . ' entries, ' . count($all) . " keys used in code\n";
}
echo $problems ? "\n$problems problem(s)\n" : "\ni18n OK\n";
exit($problems ? 1 : 0);

<?php
declare(strict_types=1);

namespace TimeTracker;

final class View
{
    /** Renders views/$template.php inside views/$layout.php (pass null for no layout). */
    public static function render(string $template, array $vars = [], ?string $layout = 'layout'): void
    {
        $content = self::capture($template, $vars);
        if ($layout === null) {
            echo $content;
            return;
        }
        $vars['content'] = $content;
        $vars['flashes'] = take_flashes();
        $vars['user'] ??= Auth::user();
        echo self::capture($layout, $vars);
    }

    public static function capture(string $template, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require TT_ROOT . '/views/' . $template . '.php';
        return (string) ob_get_clean();
    }

    public static function partial(string $name, array $vars = []): void
    {
        echo self::capture('partials/' . $name, $vars);
    }
}

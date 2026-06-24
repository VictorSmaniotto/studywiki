<?php

namespace App\View\Components;

use Illuminate\View\Component;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class Markdown extends Component
{
    public string $html;

    private static ?GithubFlavoredMarkdownConverter $converter = null;

    public function __construct(string $content)
    {
        self::$converter ??= new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $this->html = self::$converter->convert($content)->getContent();
    }

    public function render()
    {
        return view('components.markdown');
    }
}

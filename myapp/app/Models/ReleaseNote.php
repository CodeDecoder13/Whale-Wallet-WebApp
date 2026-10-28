<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseNote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['send_email' => 'boolean', 'show_popup' => 'boolean', 'published_at' => 'datetime'];
    }

    /**
     * The body as plain blocks for the popup, the email and the preview: paragraphs, and lists from lines starting "- ".
     * No HTML is ever taken from the body, so every renderer escapes it.
     *
     * @return list<array{type: 'p', text: string}|array{type: 'ul', items: list<string>}>
     */
    public function blocks(): array
    {
        $blocks = [];

        foreach (preg_split('/\R{2,}/', trim($this->body)) as $chunk) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $chunk)), 'strlen'));
            $list = [];

            foreach ($lines as $line) {
                if (str_starts_with($line, '- ')) {
                    $list[] = trim(substr($line, 2));

                    continue;
                }

                if ($list) {
                    $blocks[] = ['type' => 'ul', 'items' => $list];
                    $list = [];
                }
                $blocks[] = ['type' => 'p', 'text' => $line];
            }

            $list && $blocks[] = ['type' => 'ul', 'items' => $list];
        }

        return $blocks;
    }

    /** What the popup needs. */
    public function toNotice(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'version' => $this->version, 'blocks' => $this->blocks()];
    }
}

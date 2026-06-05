<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

/**
 * The fetched client-side SDK together with the content encoding it was served with.
 */
final class CroctScriptContent
{
    private string $content;

    private ?string $encoding;

    public function __construct(string $content, ?string $encoding)
    {
        $this->content = $content;
        $this->encoding = $encoding;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getEncoding(): ?string
    {
        return $this->encoding;
    }
}

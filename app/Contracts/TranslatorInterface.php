<?php

namespace App\Contracts;

interface TranslatorInterface
{
    public function translate(?string $text, string $targetLanguage = 'tr'): ?string;
}

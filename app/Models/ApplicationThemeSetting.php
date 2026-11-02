<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class ApplicationThemeSetting extends Model
{
    public const DEFAULT_THEME = 'midnight';

    public const THEMES = [
        'midnight' => [
            'name' => 'Midnight Blue',
            'description' => 'Biru malam profesional dengan aksen langit yang jernih.',
            'mode' => 'dark',
            'colors' => ['#0f172a', '#172554', '#0ea5e9', '#e0f2fe'],
        ],
        'emerald' => [
            'name' => 'Emerald Forest',
            'description' => 'Hijau zamrud tenang dengan kontras tinggi untuk operasional.',
            'mode' => 'dark',
            'colors' => ['#071a16', '#064e3b', '#10b981', '#d1fae5'],
        ],
        'violet' => [
            'name' => 'Royal Violet',
            'description' => 'Ungu modern yang elegan untuk tampilan premium.',
            'mode' => 'dark',
            'colors' => ['#171126', '#3b1764', '#8b5cf6', '#ede9fe'],
        ],
        'graphite' => [
            'name' => 'Graphite',
            'description' => 'Abu netral minimalis dengan aksen perak kebiruan.',
            'mode' => 'dark',
            'colors' => ['#111418', '#252a31', '#64748b', '#f1f5f9'],
        ],
        'ocean' => [
            'name' => 'Ocean Teal',
            'description' => 'Perpaduan teal dan cyan yang segar namun tetap nyaman dibaca.',
            'mode' => 'dark',
            'colors' => ['#071b22', '#0e4f5c', '#06b6d4', '#cffafe'],
        ],
        'daylight' => [
            'name' => 'Daylight Blue',
            'description' => 'Biru cerah profesional dengan permukaan putih yang bersih.',
            'mode' => 'light',
            'colors' => ['#f8fafc', '#dbeafe', '#2563eb', '#172554'],
        ],
        'mint' => [
            'name' => 'Fresh Mint',
            'description' => 'Hijau mint ringan untuk suasana kerja yang segar dan tenang.',
            'mode' => 'light',
            'colors' => ['#f7fee7', '#d1fae5', '#047857', '#064e3b'],
        ],
        'lavender' => [
            'name' => 'Soft Lavender',
            'description' => 'Lavender lembut dengan aksen ungu yang tetap tegas.',
            'mode' => 'light',
            'colors' => ['#faf5ff', '#ede9fe', '#7c3aed', '#3b0764'],
        ],
        'sand' => [
            'name' => 'Warm Sand',
            'description' => 'Warna pasir hangat dengan aksen amber yang nyaman dipandang.',
            'mode' => 'light',
            'colors' => ['#fffbeb', '#fef3c7', '#b45309', '#451a03'],
        ],
        'rose' => [
            'name' => 'Rose Quartz',
            'description' => 'Merah muda netral dengan aksen rose modern dan elegan.',
            'mode' => 'light',
            'colors' => ['#fff7f8', '#ffe4e6', '#be123c', '#4c0519'],
        ],
    ];

    protected $fillable = ['theme'];

    public static function activeTheme(): string
    {
        try {
            $theme = (string) (self::query()->value('theme') ?? self::DEFAULT_THEME);
        } catch (Throwable) {
            return self::DEFAULT_THEME;
        }

        return array_key_exists($theme, self::THEMES) ? $theme : self::DEFAULT_THEME;
    }

    public static function activeMode(): string
    {
        return self::THEMES[self::activeTheme()]['mode'];
    }
}

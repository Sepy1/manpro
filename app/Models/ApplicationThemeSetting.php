<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class ApplicationThemeSetting extends Model
{
    public const DEFAULT_THEME = 'midnight';
    public const DEFAULT_FONT_FAMILY = 'figtree';
    public const DEFAULT_FONT_SIZE = 'normal';
    public const DEFAULT_FONT_WEIGHT = 'normal';

    public const FONT_FAMILIES = [
        'figtree' => ['name' => 'Figtree', 'description' => 'Modern dan ringkas', 'stack' => 'Figtree, Inter, ui-sans-serif, system-ui, sans-serif'],
        'system' => ['name' => 'System UI', 'description' => 'Natural sesuai perangkat', 'stack' => 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, sans-serif'],
        'segoe' => ['name' => 'Segoe UI', 'description' => 'Jelas dan familiar', 'stack' => '"Segoe UI", Arial, sans-serif'],
        'arial' => ['name' => 'Arial', 'description' => 'Sederhana dan universal', 'stack' => 'Arial, Helvetica, sans-serif'],
        'georgia' => ['name' => 'Georgia', 'description' => 'Formal dengan karakter serif', 'stack' => 'Georgia, "Times New Roman", serif'],
    ];

    public const FONT_SIZES = [
        'compact' => ['name' => 'Ringkas', 'value' => '75%', 'description' => 'Lebih banyak informasi dalam satu layar'],
        'normal' => ['name' => 'Normal', 'value' => '80%', 'description' => 'Ukuran standar aplikasi'],
        'comfortable' => ['name' => 'Nyaman', 'value' => '87.5%', 'description' => 'Lebih lega dan mudah dibaca'],
        'large' => ['name' => 'Besar', 'value' => '95%', 'description' => 'Keterbacaan maksimal'],
    ];

    public const FONT_WEIGHTS = [
        'normal' => ['name' => 'Normal', 'value' => '400', 'description' => 'Ketebalan standar untuk teks aplikasi'],
        'bold' => ['name' => 'Bold', 'value' => '700', 'description' => 'Teks lebih tegas dan mudah terlihat'],
    ];

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
        'elastic' => [
            'name' => 'Elastic',
            'description' => 'Dashboard terang bergaya Elastic dengan panel putih, aksen cyan, dan sidebar bersih.',
            'mode' => 'light',
            'colors' => ['#f5f7fa', '#ffffff', '#00bfb3', '#343741'],
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

    protected $fillable = ['theme', 'font_family', 'font_size', 'font_weight', 'logo_path', 'login_logo_path'];

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

    public static function activeFontFamily(): string
    {
        try {
            $font = (string) (self::query()->value('font_family') ?? self::DEFAULT_FONT_FAMILY);
        } catch (Throwable) {
            return self::DEFAULT_FONT_FAMILY;
        }

        return array_key_exists($font, self::FONT_FAMILIES) ? $font : self::DEFAULT_FONT_FAMILY;
    }

    public static function activeFontSize(): string
    {
        try {
            $size = (string) (self::query()->value('font_size') ?? self::DEFAULT_FONT_SIZE);
        } catch (Throwable) {
            return self::DEFAULT_FONT_SIZE;
        }

        return array_key_exists($size, self::FONT_SIZES) ? $size : self::DEFAULT_FONT_SIZE;
    }

    public static function activeFontWeight(): string
    {
        try {
            $weight = (string) (self::query()->value('font_weight') ?? self::DEFAULT_FONT_WEIGHT);
        } catch (Throwable) {
            return self::DEFAULT_FONT_WEIGHT;
        }

        return array_key_exists($weight, self::FONT_WEIGHTS) ? $weight : self::DEFAULT_FONT_WEIGHT;
    }

    public static function activeLogoPath(): ?string
    {
        try {
            $path = self::query()->value('logo_path');
        } catch (Throwable) {
            return null;
        }

        return is_string($path) && $path !== '' ? $path : null;
    }

    public static function activeLoginLogoPath(): ?string
    {
        try {
            $path = self::query()->value('login_logo_path');
        } catch (Throwable) {
            return null;
        }

        return is_string($path) && $path !== '' ? $path : null;
    }
}

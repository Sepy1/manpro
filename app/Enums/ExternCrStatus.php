<?php

namespace App\Enums;

enum ExternCrStatus: string
{
    case Open = 'open';

    case VendorDevelopment = 'vendor_development';

    case Uat = 'uat';

    case GoLive = 'go_live';

    case Closed = 'closed';

    /** Status yang boleh diubah oleh PIC vendor. */
    public static function vendorPipelineCases(): array
    {
        return [
            self::VendorDevelopment,
            self::Uat,
            self::GoLive,
        ];
    }

    /**
     * @return list<string>
     */
    public static function vendorPipelineValues(): array
    {
        return array_map(
            static fn (self $case) => $case->value,
            self::vendorPipelineCases()
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::VendorDevelopment => 'Vendor Development',
            self::Uat => 'UAT',
            self::GoLive => 'Go-Live',
            self::Closed => 'Closed',
        };
    }

    /** Kelas Tailwind untuk badge status di daftar CR. */
    public function listBadgeClasses(): string
    {
        return match ($this) {
            self::Open => 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-600 dark:bg-sky-950/45 dark:text-sky-100',
            self::VendorDevelopment => 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-600 dark:bg-amber-950/40 dark:text-amber-100',
            self::Uat => 'border-violet-300 bg-violet-50 text-violet-950 dark:border-violet-600 dark:bg-violet-950/40 dark:text-violet-100',
            self::GoLive => 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-100',
            self::Closed => 'border-slate-400 bg-slate-100 text-slate-800 dark:border-slate-500 dark:bg-slate-800/80 dark:text-slate-100',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

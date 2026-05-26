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

    /** Kelas Tailwind dasar badge status di daftar CR. */
    public static function listBadgeShellClasses(): string
    {
        return 'cr-status-chip inline-flex max-w-[13rem] items-center rounded-lg border px-2.5 py-1 text-xs font-medium text-slate-950 dark:text-white';
    }

    /** Kelas Tailwind untuk badge status di daftar CR (sama admin & vendor). */
    public function listBadgeClasses(): string
    {
        return 'border-teal-500 bg-teal-50 text-teal-700 dark:border-teal-400 dark:bg-teal-950/40 dark:text-teal-200';
    }

    /**
     * @return array<string, string>
     */
    public static function listBadgeClassMap(): array
    {
        $map = [];

        foreach (self::cases() as $case) {
            $map[$case->value] = $case->listBadgeClasses();
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

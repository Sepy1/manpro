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

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

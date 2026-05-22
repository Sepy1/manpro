<?php

namespace App\Enums;

enum ExternCrStatus: string
{
    case Open = 'open';

    case VendorDevelopment = 'vendor_development';

    case Uat = 'uat';

    case GoLive = 'go_live';

    case Closed = 'closed';

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

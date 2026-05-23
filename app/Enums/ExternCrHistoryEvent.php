<?php

namespace App\Enums;

enum ExternCrHistoryEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case AttachmentAdded = 'attachment_added';
    case AttachmentDeleted = 'attachment_deleted';
    case WhatsappAuthorization = 'whatsapp_authorization';
    case WaAuthorizationInviteDispatched = 'wa_authorization_invite_dispatched';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Dibuat',
            self::Updated => 'Diperbarui',
            self::StatusChanged => 'Status',
            self::AttachmentAdded => 'Lampiran ditambah',
            self::AttachmentDeleted => 'Lampiran dihapus',
            self::WhatsappAuthorization => 'Otorisasi WhatsApp',
            self::WaAuthorizationInviteDispatched => 'Undangan otorisasi WA',
        };
    }
}

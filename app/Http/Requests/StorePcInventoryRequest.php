<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePcInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:255'],
            'machine_guid' => ['required', 'string', 'max:255'],
            'serial_number' => ['required', 'string', 'max:255'],
            'mainboard_type' => ['required', 'string', 'max:255'],
            'antivirus_status' => ['required', 'string', 'max:50'],
            'antivirus_name' => ['required', 'string', 'max:500'],
            'os_info' => ['required', 'string', 'max:255'],
            'cpu_name' => ['required', 'string', 'max:255'],
            'cpu_cores' => ['required', 'integer', 'min:1', 'max:1024'],
            'cpu_threads' => ['required', 'integer', 'min:1', 'max:2048'],
            'ram_gb' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'disk_total_gb' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'disk_free_gb' => ['required', 'numeric', 'min:0', 'lte:disk_total_gb'],
            'disk_detail' => ['present', 'string', 'max:5000'],
            'mac_address' => ['required', 'string', 'max:50'],
            'ip_address' => ['required', 'string', 'max:50'],
            'kantor_id' => [
                'required',
                'integer',
                Rule::exists('kantors', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'kas_kantor_id' => [
                'nullable',
                'integer',
                Rule::exists('kas_kantor', 'id')->where(fn ($query) => $query
                    ->where('kantor_id', $this->integer('kantor_id'))
                    ->where('is_active', true)),
            ],
            'nama_user' => ['required', 'string', 'max:255'],
            'jabatan' => ['required', 'string', Rule::in([
                'Kepala Divisi',
                'Kepala Cabang',
                'Kepala Kantor Kas',
                'Kepala Bidang',
                'Kepala Sub Bidang',
                'Staf / AO / Admin',
                'Teller',
                'Customer Service',
            ])],
            'status_perangkat' => ['required', 'string', Rule::in([
                'Inventaris',
                'Device Pribadi (BYOD)',
            ])],
        ];
    }
}

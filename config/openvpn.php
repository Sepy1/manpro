<?php

return [
    'connection' => env('OPENVPN_CONNECTION', 'local'),
    'ssh' => [
        'host' => env('OPENVPN_SSH_HOST'),
        'port' => (int) env('OPENVPN_SSH_PORT', 22),
        'user' => env('OPENVPN_SSH_USER', 'manpro-vpn'),
        'key' => env('OPENVPN_SSH_KEY'),
        'known_hosts' => env('OPENVPN_SSH_KNOWN_HOSTS'),
        'connect_timeout' => (int) env('OPENVPN_SSH_CONNECT_TIMEOUT', 5),
    ],
    'remote_commands' => [
        'management' => '/usr/local/sbin/manpro-openvpn',
        'pki' => '/usr/local/sbin/manpro-openvpn-pki',
        'profile' => '/usr/local/sbin/manpro-openvpn-profile',
        'service' => '/usr/local/sbin/manpro-openvpn-service',
        'policy' => '/usr/local/sbin/manpro-openvpn-policy',
    ],
    'server_config' => env('OPENVPN_SERVER_CONFIG', '/etc/openvpn/server/server.conf'),
    'server_dir' => env('OPENVPN_SERVER_DIR', '/etc/openvpn/server'),
    'ccd_dir' => env('OPENVPN_CCD_DIR', '/etc/openvpn/ccd'),
    'easy_rsa_dir' => env('OPENVPN_EASY_RSA_DIR', '/etc/openvpn/easy-rsa'),
    'pki_dir' => env('OPENVPN_PKI_DIR', '/etc/openvpn/easy-rsa/pki'),
    'service' => env('OPENVPN_SERVICE', 'openvpn-server@server'),
    'status_log' => env('OPENVPN_STATUS_LOG', '/var/log/openvpn/status.log'),
    'client_template' => env('OPENVPN_CLIENT_TEMPLATE', '/etc/openvpn/client-template.txt'),
    'client_output_dir' => env('OPENVPN_CLIENT_OUTPUT_DIR', storage_path('app/private/openvpn')),
    'network' => env('OPENVPN_NETWORK', '10.200.0.0/24'),
    'server_ip' => env('OPENVPN_SERVER_IP', '10.200.0.1'),
    'management_host' => env('OPENVPN_MANAGEMENT_HOST', '127.0.0.1'),
    'management_port' => (int) env('OPENVPN_MANAGEMENT_PORT', 0),
    'command_timeout' => (int) env('OPENVPN_COMMAND_TIMEOUT', 30),
];

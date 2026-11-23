<?php

namespace App\Enums;

enum ScanType: string
{
    case SecurityScanner = 'security_scanner';
    case WebsiteAudit = 'website_audit';
}

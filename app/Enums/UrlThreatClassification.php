<?php

namespace App\Enums;

enum UrlThreatClassification: string
{
    case Malicious = 'malicious';
    case Suspicious = 'suspicious';
    case NoThreatDetected = 'no_threat_detected';
    case UnableToVerify = 'unable_to_verify';
    case IntegrationUnavailable = 'integration_unavailable';
}

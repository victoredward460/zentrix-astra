<?php

namespace App\Services\Scanner;

use App\Data\HttpProbeResult;

interface HttpProbe
{
    public function request(string $scheme, string $hostname, string $address): HttpProbeResult;
}

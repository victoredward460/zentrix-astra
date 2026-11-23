<?php

namespace App\Services\Scanner;

use App\Data\HttpProbeResult;

interface WebsitePageProbe
{
    public function fetchDocument(string $hostname, string $address): HttpProbeResult;
}

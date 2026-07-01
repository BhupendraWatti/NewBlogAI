<?php

namespace App\Modules\SiteManager\Events;

use App\Modules\SiteManager\Models\Site;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteSyncTriggered
{
    use Dispatchable, SerializesModels;

    public function __construct(public Site $site)
    {
        //
    }
}

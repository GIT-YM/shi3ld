<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('shi3ld:deliver-enquiries')->everyMinute()->withoutOverlapping(10);

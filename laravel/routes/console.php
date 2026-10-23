<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('shi3ld:deliver-enquiries')->everyMinute()->withoutOverlapping(10);

Schedule::command('shi3ld:builder:process')->everyMinute()->withoutOverlapping(5);

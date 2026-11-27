<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('stockflow:monitor')->dailyAt('06:00');
Schedule::command('stockflow:monitor')->dailyAt('18:00');

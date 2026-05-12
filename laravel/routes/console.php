<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('cdapi:coding:auto-reopen')->everyFiveMinutes();

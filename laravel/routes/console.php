<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('coding:auto-reopen')->everyFiveMinutes();

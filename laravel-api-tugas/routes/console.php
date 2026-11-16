<?php

use Illuminate\Support\Facades\Schedule;

// Pemeriksaan stok minimum dan kedaluwarsa dua kali sehari (FR-23, jadwal ERP PRD 9.4)
// memakai zona waktu aplikasi Asia/Jakarta (WIB).
Schedule::command('stockflow:monitor')->dailyAt('06:00');
Schedule::command('stockflow:monitor')->dailyAt('18:00');

<?php

return [
    /*
     * Number of most-recent games to automatically queue for analysis after a sync.
     * Set to 0 to disable auto-analysis on sync entirely.
     */
    'auto_analyse_on_sync' => (int) env('CHESS_AUTO_ANALYSE_ON_SYNC', 5),
];

<?php

return [
    /*
     * Number of most-recent games to automatically queue for analysis after a sync.
     * Set to 0 to disable auto-analysis on sync entirely.
     */
    'auto_analyse_on_sync' => (int) env('CHESS_AUTO_ANALYSE_ON_SYNC', 5),

    /*
     * Monthly analysis quota for free-tier users. Premium users have no limit.
     * Set to 0 to effectively disable analysis for free users.
     */
    'free_monthly_analysis_quota' => (int) env('CHESS_FREE_MONTHLY_ANALYSIS_QUOTA', 10),
];

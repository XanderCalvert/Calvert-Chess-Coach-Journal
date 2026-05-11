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

    /*
     * Number of most-recent analysed games to include in each weakness profile snapshot.
     */
    'weakness_profile_window' => (int) env('CHESS_WEAKNESS_PROFILE_WINDOW', 20),

    /*
     * Minimum number of games in a single ECO/opening before it appears in opening_breakdown.
     */
    'weakness_opening_min_games' => (int) env('CHESS_WEAKNESS_OPENING_MIN_GAMES', 3),

    /*
     * Minimum number of distinct games a tactical motif must appear in before it appears in motif_frequencies.
     */
    'weakness_motif_min_games' => (int) env('CHESS_WEAKNESS_MOTIF_MIN_GAMES', 2),
];

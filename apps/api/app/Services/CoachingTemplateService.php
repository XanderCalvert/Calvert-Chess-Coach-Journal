<?php

namespace App\Services;

use App\Enums\MoveClassification;

/**
 * Generates risk_note prose from structured coaching data.
 * risk_note is optional copy — the structured fields are the source of truth.
 */
class CoachingTemplateService
{
    public function buildRiskNote(
        MoveClassification $classification,
        array $themes,
        array $tacticalFlags,
        array $threatAwareness,
    ): ?string {
        // Priority 1: forced mate available
        if (in_array('forced_mate_present', $tacticalFlags, true)) {
            return $this->mateNote($classification);
        }

        // Priority 2: engine-preferred capture (strong signal)
        if (in_array('engine_prefers_capture', $tacticalFlags, true)) {
            return $this->captureNote($classification, $threatAwareness);
        }

        // Priority 3: hanging piece
        if (in_array('hanging_piece', $tacticalFlags, true)) {
            return $this->hangingNote($classification, $threatAwareness);
        }

        // Priority 4: classification message (only for mistakes/blunders — no noise on clean moves)
        if (in_array($classification, [MoveClassification::Mistake, MoveClassification::Blunder], true)) {
            return $this->classificationNote($classification, $themes);
        }

        // Priority 5: theme label (only for inaccuracies, not clean moves)
        if ($classification === MoveClassification::Inaccuracy && !empty($themes)) {
            return $this->themeNote($themes);
        }

        // Best/Excellent/Good with no tactical flags — no note needed
        return null;
    }

    // -------------------------------------------------------------------------

    private function mateNote(MoveClassification $classification): string
    {
        if (in_array($classification, [MoveClassification::Best, MoveClassification::Excellent], true)) {
            return 'Forced checkmate sequence available — best play.';
        }
        return 'A forced checkmate was available but not played.';
    }

    private function captureNote(MoveClassification $classification, array $threatAwareness): string
    {
        $response = $threatAwareness['response'] ?? 'unknown';
        if ($response === 'addressed') {
            return 'The engine-recommended capture was played, resolving the material imbalance.';
        }
        if (in_array($classification, [MoveClassification::Mistake, MoveClassification::Blunder], true)) {
            return 'A winning capture was available but missed.';
        }
        return 'The engine recommended a capture here.';
    }

    private function hangingNote(MoveClassification $classification, array $threatAwareness): string
    {
        $response = $threatAwareness['response'] ?? 'unknown';
        if ($response === 'not_addressed') {
            return 'A piece appears to be hanging — this move did not address the threat.';
        }
        if ($response === 'addressed') {
            return 'The hanging piece threat was resolved.';
        }
        return 'A piece may be left undefended after this move.';
    }

    private function classificationNote(MoveClassification $classification, array $themes): string
    {
        $themeHint = !empty($themes) ? ' (' . $this->themeLabel($themes[0]) . ')' : '';
        return match ($classification) {
            MoveClassification::Blunder  => "Blunder{$themeHint} — significant material or positional loss.",
            MoveClassification::Mistake  => "Mistake{$themeHint} — a better move was available.",
            default                      => '',
        };
    }

    private function themeNote(array $themes): string
    {
        return 'Slight inaccuracy — consider ' . $this->themeLabel($themes[0]) . ' principles.';
    }

    private function themeLabel(string $theme): string
    {
        return match ($theme) {
            'development'    => 'piece development',
            'center_control' => 'center control',
            'material'       => 'material balance',
            'king_safety'    => 'king safety',
            'activity'       => 'piece activity',
            default          => $theme,
        };
    }
}

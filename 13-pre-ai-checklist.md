# Pre–AI integration checklist

Reference doc: what to finish or decide **before** (or as the first slice of) the LLM / narration layer. Complements [09-build-roadmap.md](./09-build-roadmap.md) Phase 4, [06-explanation-system.md](./06-explanation-system.md), and the gaps listed in [00-index.md](./00-index.md).

---

## Worth doing before leaning on an LLM

These reduce cost surprises, inconsistent UX, and prompt churn.

1. **Manual PGN pipeline parity**  
   Sync imports are staged and quota-aware; **`POST /api/v1/games` still auto-dispatches analysis** in the current product gap. Align manual import with “metadata cheap, analyse explicit” so behaviour, quota, and future “generate explanations” actions match everywhere.

2. **Stable contract for model input**  
   You already expose deterministic context (eval, best move/line, phase, tags, `risk_note`, weakness aggregates). **Freeze a single JSON shape** per key moment (or per move) that the narrator consumes, **version** it (align with `coaching_version` / a dedicated prompt or schema version), and **test serialization** so prompt refactors do not silently drop fields.

3. **`explanation_status` end-to-end**  
   Key moments can carry `explanation_status` (e.g. not requested → queued → complete / failed). Decide: who transitions state (dedicated job vs inline), how **stale** is defined after re-analysis, and how the UI shows **loading / error / cached** without blocking the rest of the game view.

4. **Explicit user actions for AI cost**  
   Match roadmap intent: **no auto-retry loops**, **Generate** vs **Regenerate**, throttles, and clarity on how **monthly analysis quota** relates to **LLM usage** (same bucket vs separate later). Protects bills and support load.

5. **No-auth demo path**  
   For anyone judging explanation quality without onboarding: **`/demo`**, seeded analysed games, and optionally an engineering/case-study page. Otherwise every LLM iteration depends on live sync + analyse.

6. **Deploy-shaped basics**  
   Staging URL, **queue workers**, and visibility into **failed jobs** (analysis already async; LLM adds timeouts, rate limits, parse failures). AI increases operational surface area.

---

## Nice to have before AI (good ROI, not strict blockers)

- **Show heuristic tag labels on key-moment cards** — tags are already stored server-side; readable labels help users and improve prompt grounding.  
- **“Wrong tag?” / feedback** — lighter than full user override; builds trust early.  
- **Journal / notes / coach agreement** — great future context for prompts; can ship after v1 explanations with a smaller prompt.  
- **Dedicated `/patterns` or dashboard** — profile + weakness profile already surface trend signal; extra routes are mostly navigation and copy.  
- **Walk through [06-explanation-system.md](./06-explanation-system.md)** — use the validation and anti-hallucination checklist as a pre-flight so implementation matches intent on day one.

---

## Already in good shape (no need to “complete” further first)

- Engine path, key-moment selection, deterministic copy in UI, staged `analysis_status`, polling, analysis quota, weakness aggregation — this is the **structured layer** Phase 4 describes the narrator as consuming, not replacing.

---

## One-line priority summary

**Highest leverage before AI:** manual PGN staging + **versioned model-input payload** + **explanation state machine** + **explicit generate/regenerate + cost UX**, plus **demo/deploy** if non-builders will judge quality.

---

## Related docs

| Doc | Why |
|-----|-----|
| [09-build-roadmap.md](./09-build-roadmap.md) | Phase 3.5 gaps, Phase 4 AI narration bullets |
| [06-explanation-system.md](./06-explanation-system.md) | Prompt design, depth, validation |
| [05-analysis-pipeline.md](./05-analysis-pipeline.md) | Where explanations sit in the async pipeline |
| [00-index.md](./00-index.md) | Current “not done” MVP list |
